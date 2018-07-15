<?php
$dir_path = get_param('dir');
$proj_id = get_param('proj');
$repo_id = get_param('repo');
$branch_name = get_param('branch');

new AutoDeployManager($dir_path, $proj_id, $repo_id, $branch_name);

/**
 * Helper
 */
function get_param($key, $default = null) {
  return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
}
function html_escape($string) {
  return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
function output($string) {
  echo html_escape($string);
}
function mycmd() {
  $arg_list = func_get_args();
  $format = array_shift($arg_list);
  foreach ($arg_list as &$arg) {
    $arg = escapeshellarg($arg);
  }
  return vsprintf($format, $arg_list);
}
function myexec($command, &$output = null, &$return_var = null) {
  exec($command, $output, $return_var);
  return $return_var === 0;
}

/**
 * class AutoDeployManager
 */
class AutoDeployManager {
  const SPACE_ID = 'SPACE_ID';
  const DOCUMENT_ROOT = '/var/www/hosts/web';
  const REPO_DIR_NAME = '.htdocs';
  const HTACCESS_TEMPLATE_PATH = '/var/www/hosts/example/.htaccess_auto_deploy';

  /** AutoDeployManager($arg_dir_path, $arg_proj_id, $arg_repo_id, $arg_branch_name)
   * @param {string $arg_dir_path} 自動デプロイ対象にするドキュメントルート下のディレクトリパス
   * @param {string $arg_proj_id} git clone URL に含まれるプロジェクト名
   * @param {string $arg_repo_id} git clone URL に含まれるリポジトリ名
   * @param {string $arg_branch_name} 使用するブランチ名（省略可能）
   *
   * Example:
   *   work_dir_path   : /var/www/hosts/web/exp
   *   clone_url       : SPACE_ID@SPACE_ID.git.backlog.jp:/PROJ/repo.git
   *   arg_dir_path    : exp
   *   arg_proj_id     : PROJ
   *   arg_repo_id     : repo
   *   arg_branch_name : master
   */
  public function __construct($arg_dir_path, $arg_proj_id, $arg_repo_id, $arg_branch_name = null) {
    $this->arg_dir_path = $arg_dir_path;
    $this->arg_proj_id = $arg_proj_id;
    $this->arg_repo_id = $arg_repo_id;
    $this->arg_branch_name = $arg_branch_name;

    $this->executeMain();
  }

  public function executeMain() {
    $this->isDebug = false;

    $this->work_dir_created = false;

    try {
      $this->initAndValidate();
      $this->will_git_clone();
      $this->git_update();

      $result = 'Succeeded.';
    }
    catch (Exception $e) {
      // work_dir_path をこのリクエストで新規作成した場合に、
      // git clone に失敗して work_dir_path が空であれば削除しておく
      if ($this->work_dir_created) {
        rmdir($this->work_dir_path);
      }

      if ($this->isDebug) {
        $result = $e->getMessage();
      }
      else {
        $result = 'Failed.';
      }
    }

    $result = ['result' => $result];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
  }

  /** initAndValidate()
   * リクエストパラメータから自動デプロイに必要な情報を組み立てる
   *
   * Example:
   *   arg_dir_path     : exp
   *   work_dir_path    : /var/www/hosts/web/exp
   *   htaccess_path    : /var/www/hosts/web/exp/.htaccess
   *   repo_dir_path    : /var/www/hosts/web/exp/.htdocs
   *   dot_git_dir_path : /var/www/hosts/web/exp/.htdocs/.git
   *   clone_url        : SPACE_ID@SPACE_ID.git.backlog.jp:/PROJ/repo.git
   */
  public function initAndValidate() {
    // arg_dir_path から、連続するスラッシュを1個にし、先頭と末尾のスラッシュを取り除く
    $this->arg_dir_path = preg_replace('!^/|/$!', '', preg_replace('!/+!', '/', $this->arg_dir_path));

    // arg_dir_path が指定されていない場合や、許容するパターン（英数、アンダースコア、ハイフン、スラッシュ、ドット）にマッチしない場合
    if (!preg_match('!^[\./0-9A-Za-z_\-]+$!', $this->arg_dir_path)) throw new Exception('[arg_dir_path] is invalid.');
    // arg_dir_path に連続するドットが含まれている場合
    if (preg_match('!\.\.!', $this->arg_dir_path)) throw new Exception('[arg_dir_path] is invalid.');
    // arg_proj_id が指定されていない場合や、許容するパターン（英数、アンダースコア、ハイフン）にマッチしない場合
    if (!preg_match('!^[0-9A-Za-z_\-]+$!', $this->arg_proj_id)) throw new Exception('[arg_proj_id] is invalid.');
    // arg_repo_id が指定されていない場合や、許容するパターン（英数、アンダースコア、ハイフン、ドット）にマッチしない場合
    if (!preg_match('!^[\.0-9A-Za-z_\-]+$!', $this->arg_repo_id)) throw new Exception('[arg_repo_id] is invalid.');

    // arg_dir_path で ssl/ 以下は指定できないようにする
    if (preg_match('!^ssl(?:/|$)!', $this->arg_dir_path)) throw new Exception('[arg_dir_path] cannot assign a directory included in ssl.');

    // arg_branch_name が許容する文字（制御文字を除く ASCII 文字）以外を含む場合
    if (preg_match('/[^\x20-\x7e]/', $this->arg_branch_name)) throw new Exception('[arg_branch_name] is invalid.');
    // arg_branch_name を正規化する
    if (is_null($this->arg_branch_name) || $this->arg_branch_name === '') $this->arg_branch_name = 'master';

    $this->work_dir_path = sprintf('%s/%s', self::DOCUMENT_ROOT, $this->arg_dir_path);
    $this->htaccess_path = sprintf('%s/.htaccess', $this->work_dir_path);
    $this->repo_dir_path = sprintf('%s/%s', $this->work_dir_path, self::REPO_DIR_NAME);
    $this->dot_git_dir_path = sprintf('%s/.git', $this->repo_dir_path);

    $this->clone_url = sprintf('%s@%s.git.backlog.jp:/%s/%s.git', self::SPACE_ID, self::SPACE_ID, $this->arg_proj_id, $this->arg_repo_id);
    $this->branch_name = $this->arg_branch_name;
  }

  public function will_git_clone() {
    // 既に git clone している場合
    if (is_dir($this->dot_git_dir_path)) return;

    // work_dir_path ディレクトリが存在しなければ作成する
    if (!is_writable(self::DOCUMENT_ROOT)) throw new Exception('[DOCUMENT_ROOT] is not writable.');
    if (!is_dir($this->work_dir_path)) {
      mkdir($this->work_dir_path, 0777, true);
      $this->work_dir_created = true;
    }
    if (!is_dir($this->work_dir_path)) throw new Exception('[work_dir_path] is not created.');

    // work_dir_path ディレクトリに移動する
    $isSuccess = chdir($this->work_dir_path);
    if (!$isSuccess) throw new Exception('[work_dir_path] cannot move.');

    // git clone を実行する
    $cmd = mycmd('git clone %s %s', $this->clone_url, self::REPO_DIR_NAME);
    myexec($cmd);
    if (!is_dir($this->dot_git_dir_path)) throw new Exception('git clone failed.');

    // .htaccess を更新する
    $this->update_htaccess();
  }

  public function update_htaccess() {
    if (!is_readable(self::HTACCESS_TEMPLATE_PATH)) throw new Exception('[HTACCESS_TEMPLATE_PATH] is not readable.');
    if (is_file($this->htaccess_path) && !is_writable($this->htaccess_path)) throw new Exception('[htaccess_path] is not writable.');
    if (!is_writable($this->work_dir_path)) throw new Exception('[work_dir_path] is not writable.');

    $htaccess_auto_deploy = file_get_contents(self::HTACCESS_TEMPLATE_PATH);
    $htaccess_auto_deploy = str_replace('${arg_dir_path}', $this->arg_dir_path, $htaccess_auto_deploy);
    $content = '';

    // .htaccess が存在している場合は、既存の記述を取得する
    if (is_file($this->htaccess_path) && is_readable($this->htaccess_path)) {
      $content = file_get_contents($this->htaccess_path);
      if ($content !== '') $content .= "\n";
    }

    // auto_deploy 用の記述を追記する
    $content .= $htaccess_auto_deploy;
    file_put_contents($this->htaccess_path, $content);
  }

  public function git_update() {
    // git 管理のディレクトリが存在しない場合
    if (!is_dir($this->dot_git_dir_path)) throw new Exception('[dot_git_dir_path] is not exist.');

    // repo_dir_path ディレクトリに移動する
    $isSuccess = chdir($this->repo_dir_path);
    if (!$isSuccess) throw new Exception('[repo_dir_path] cannot move.');

    // 既存のリポジトリの clone url を取得する
    $cmd = mycmd('git remote -v');
    $isSuccess = myexec($cmd, $output);
    if (!$isSuccess || empty($output)) throw new Exception('git remote failed.');
    $strs = preg_split('/\s+/', $output[0]);
    $clone_url = isset($strs[1]) ? $strs[1] : null;

    // clone url (proj, repo) が異なる場合
    if ($clone_url !== $this->clone_url) throw new Exception('[clone_url] another repository already exists.');

    // git fetch でリモートブランチの情報を取得する
    $cmd = mycmd('git fetch');
    $isSuccess = myexec($cmd, $output);
    if (!$isSuccess) throw new Exception('git fetch failed.');

    // git checkout でローカルブランチを設定する
    $cmd = mycmd('git checkout %s', $this->branch_name);
    $isSuccess = myexec($cmd, $output);
    if (!$isSuccess) throw new Exception('[branch_name] git checkout failed.');

    // git merge でローカルブランチを更新する
    $arg = 'origin/' . $this->branch_name;
    $cmd = mycmd('git merge %s', $arg);
    $isSuccess = myexec($cmd, $output);
    if (!$isSuccess) throw new Exception('[branch_name] git merge failed.');
  }
}

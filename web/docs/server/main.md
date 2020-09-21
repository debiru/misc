# まえがき

この文書は、2017年時点の作業内容をまとめてたものです。

- さくらVPSに Ubuntu をインストールして Web サーバを立てる
- サーバの IP は 203.0.113.123 とする（[例示用 IP](http://tools.ietf.org/html/rfc6890#page-12)）
- 割り当てるドメイン名は example.org とする（[例示用ドメイン名](https://tools.ietf.org/html/rfc2606#page-3)）

# 利用したいドメイン名を登録する

- example.org を登録する
    - 例えば [Amazon Route 53](https://aws.amazon.com/jp/route53/) を利用する
    - jp トップレベルドメインの場合は Amazon Route 53 では登録料が高いので、他のドメインレジストラで登録した方がよい
- 権威DNSサーバに DNS RRs を設定する
    - 権威DNSサーバを独自に構築してもよいが、ここでは Amazon Route 53 が提供しているゾーンサーバを使うことにする
    - A, MX, TXT などのレコードを追加する

| Host | Type | Value | TTL |
| :---: | :---: | :---: | :---: |
| example.org | A | 203.0.113.123 | 3600 |
| *.example.org | A | 203.0.113.123 | 3600 |
| example.org | AAAA | xxxx:xxxx:...:xxxx:xxxx | 3600 |
| *.example.org | AAAA | xxxx:xxxx:...:xxxx:xxxx | 3600 |
| example.org | MX | 10 example.org | 3600 |
| example.org | TXT | "v=spf1 +mx -all" | 3600 |

- NOTE: DNS レコードの設定に際しては、フルリゾルバにキャッシュが残らないように注意する必要がある。レコードの変更時には予めその RRs の TTL を短くし、設定の確認時には（フルリゾルバに対する再帰問い合わせを行わず）権威DNSサーバに対して非再帰問い合わせを行う。
    - 面倒であれば `dig example.org +trace` のように dig の trace オプションを用いてもよい。

# さくらVPSコントロールパネル

- カスタムOSのインストールで Ubuntu（最新版）を選ぶ
- [VPSカスタムOSインストールガイド - Ubuntu 16.04](https://help.sakura.ad.jp/hc/ja/articles/206057762)
    1. Install を選ぶ
    2. キーボードの言語 Japanese を選ぶ
    3. キーボードレイアウトは Japanese - Japanese (...) ではなく、一番上の Japanese を選ぶ
    4. OS のユーザ名を入力する（アカウント名と同じでよい）
    5. OS のユーザアカウント名を入力する
    6. そのアカウントのパスワードを入力する
    7. Encrypt your home directory? と聞かれるので No を選ぶ
    8. パーティションの設定で Unmount partitions that are in use? と聞かれるので Yes を選ぶ
    9. Partitioning method には Guided - use entire disk を選ぶ
    10. Select disk to partition では表示されている1台目のディスクを選ぶ
    11. Write the changes to disks? では Yes を選ぶ
    12. OS インストール処理を待つ
    13. Finish the installation が表示されたら Continue を選ぶ
- コントロールパネルに戻って「起動」ボタンを押す

# OS セットアップ

## #1. SSH 接続をする

```
ssh yourname@example.org
```

- NOTE: [SSH接続](./note/ssh_connection.md)

## #2. apt を更新する

```
sudo apt update
sudo apt upgrade
```

- NOTE: パッケージ管理には [6.2. aptitude、apt-get、apt コマンド](https://www.debian.org/doc/manuals/debian-handbook/sect.apt-get.ja.html) といくつかのコマンドが存在するが、apt が推奨されている

## #3. add-apt-repository をインストールする

- `add-apt-repository` は `software-properties-common` のインストールによって使えるようになる

```
sudo apt install software-properties-common
```

## #4. ~/.profile へ追記する

```
emacs ~/.profile
```

- `ls` でのディレクトリ表示色が青では暗いのでシアンにする
    - 既存の LS_COLORS を消さずに特定の値だけ書き換えたいため、簡易的に設定値が重複する形で追記している

```
# [dir => Cyan, ln => Yellow, or => Blink-Red, mi => Blink-Red]
# or = Orphaned symlink (symlinkFrom), mi = Missing file (symlinkTo; displayed by `ls -l`)
# refs: `dircolors` or `man dir_colors`
export LS_COLORS="${LS_COLORS}di=01;36:ln=01;33:or=01;05;37;41:mi=01;05;37;41:"
```

### Ubuntu における bash の ~/.profile について

Ubuntu 7.04 (Feisty Fawn) から、ホームディレクトリ内の .bash_profile の代わりに .profile が使われるようになりました。

changelog に書かれている通り、~/.bash_profile を作成してしまうと ~/.profile が読み込まれなくなってしまいます。

`$ zcat /usr/share/doc/bash/changelog.Debian.gz` または `$ apt changelog bash` とでもコマンドを打てば changelog を確認することができます。

```
bash (3.2-0ubuntu5) feisty; urgency=low

  * Install /etc/skel/.profile, instead of /etc/skel/.bash_profile.
    Users will find a ~/.profile instead of ~/.bash_profile; ~/.profile
    is not read by a bash login shell, if ~/.bash_profile still exists.
  * Fix one more crash in clear_console. Ubuntu #87402.

 -- Matthias Klose <doko@ubuntu.com>  Sat,  3 Mar 2007 14:06:07 +0100
```

## #5. テキストエディタをインストールする

```
sudo apt install emacs
sudo apt install vim
```

- 使いたいエディタをインストールする

## #6. emacs の設定をする

```
emacs ~/.emacs.d/init.el
```

```
(prefer-coding-system 'utf-8)
(setq coding-system-for-read 'utf-8)
(setq coding-system-for-write 'utf-8)
(set-language-environment "Japanese")

(require 'whitespace)
(set-face-foreground 'whitespace-space "#ccc")
(set-face-background 'whitespace-space nil)
(set-face-bold-p 'whitespace-space t)
(set-face-foreground 'whitespace-tab "#333")
(set-face-background 'whitespace-tab nil)
(set-face-underline  'whitespace-tab t)
(setq whitespace-style '(face tabs tab-mark spaces space-mark))
(setq whitespace-space-regexp "\\(\x3000+\\)")
(setq whitespace-display-mappings
'((space-mark ?\x3000 [?\□])
(tab-mark ?\t [?\xBB ?\t])
))
(global-whitespace-mode 1) ;; 全角スペースを常に表示

;; elisp のパッケージ管理のリポジトリを追加する
;;   editorconfig をインストールするために melpa リポジトリを追加しておく
;; M-x package-list-packages
;; M-x package-install PACKAGE_NAME
(require 'package)
(add-to-list 'package-archives '("melpa" . "http://melpa.milkbox.net/packages/"))
;(add-to-list 'package-archives '("marmalade" . "http://marmalade-repo.org/packages/"))
(fset 'package-desc-vers 'package--ac-desc-version)
(package-initialize)

;; M-x package-install web-mode
(require 'web-mode)
(add-to-list 'auto-mode-alist '("\\.php\\'" . web-mode))
(add-to-list 'auto-mode-alist '("\\.html\\'" . web-mode))

(defun my-web-mode-hook ()
  "Hooks for Web mode."
  (setq web-mode-markup-indent-offset 2)
  (setq web-mode-css-indent-offset 2)
  (setq web-mode-code-indent-offset 2)
  (setq web-mode-attr-indent-offset 0)
)
(add-hook 'web-mode-hook  'my-web-mode-hook)

;  (add-to-list 'web-mode-indentation-params '("lineup-args" . nil))
;  (add-to-list 'web-mode-indentation-params '("lineup-calls" . nil))
;  (add-to-list 'web-mode-indentation-params '("lineup-concats" . nil))
;  (add-to-list 'web-mode-indentation-params '("lineup-ternary" . nil))

(custom-set-faces
  '(web-mode-doctype-face
     ((t (:foreground "#808099"))))
  '(web-mode-html-tag-face
     ((t (:foreground "#6040ff"))))
  '(web-mode-html-tag-bracket-face
     ((t (:foreground "#ffffff"))))
  '(web-mode-html-attr-name-face
     ((t (:foreground "#b07000"))))
  '(web-mode-html-attr-value-face
     ((t (:foreground "#990066"))))
  '(web-mode-html-attr-equal-face
     ((t (:foreground "#ffffff"))))
  '(web-mode-comment-face
     ((t (:foreground "#ff0000"))))
  )

(require 'linum)
(global-linum-mode t)
(setq linum-format "%4d: ")

(column-number-mode t)
(line-number-mode t)

(setq-default show-trailing-whitespace t)

(setq backup-inhibited t)
(setq delete-auto-save-files t)

(setq-default indent-tabs-mode nil)
(setq lisp-indent-offset 2)

(setq js-indent-level 2)
(setq css-indent-offset 2)

(add-hook 'php-mode-hook
  (lambda ()
    (c-set-offset 'case-label' c-basic-offset)
    (c-set-offset 'arglist-intro' c-basic-offset)
    (c-set-offset 'arglist-cont-nonempty' c-basic-offset)
    (c-set-offset 'arglist-close' 0)
    ))

(global-set-key (kbd "<backtab>") (kbd "C-q <tab>"))

(global-set-key (kbd "<f6>") 'linum-mode)

(cua-mode t)
(setq cua-enable-cua-keys nil)
(global-set-key (kbd "C-z") 'cua-set-rectangle-mark)
```

- その後 `M-x package-install web-mode` で web-mode をインストールする
- `yaml-mode` もインストールする

## #7. config editor を設定する

```
sudo update-alternatives --config editor
```

- vim.tiny など好きなエディタを選択する

## #8. git 最新版をインストールする

```
sudo add-apt-repository ppa:git-core/ppa
sudo apt update
sudo apt install git
```

- git をインストールしたら[初期設定](https://git-scm.com/book/en/v2/Getting-Started-First-Time-Git-Setup)を行う

```
git config --global user.name "yourname"
git config --global user.email "yourname@example.org"
```

- `/etc/` 下を git 管理する
- 以後 `apt install` などを行う度にコミットする

## #9. git config の設定をする

```
emacs ~/.gitconfig
```

```
[user]
        name = yourname
        email = yourname@example.org
[color]
        diff = auto
        status = auto
        branch = auto
        interactive = auto
        ui = auto
[alias]
        co           = checkout
        st           = status
        di           = diff
        diw          = diff --color-words='[[:alnum:]]+|[^[:space:]]'
        push-reset   = push -f origin HEAD:master
        amend        = commit --amend
        pick         = cherry-pick
        dic          = diff --cached
        ls           = ls-files
        up-assume    = update-index --assume-unchanged
        up-no-assume = update-index --no-assume-unchanged
        up-skip      = update-index --skip-worktree
        up-no-skip   = update-index --no-skip-worktree
[core]
        pager = less -+S
[push]
        default = simple
```


## #10. 日本語パックをインストールする

```
sudo apt install language-pack-ja manpages-ja
```

## #11. システム上のロケールを UTF-8 に設定する

```
sudo update-locale LANG="en_US.UTF-8"
```

`/etc/default/locale` のファイルが更新される。

```
#  File generated by update-locale
LANG=en_US.UTF-8
LANGUAGE="en_US:"
```

## #12. /etc/hostname の設定をする

- hostname には FQDN ではなくホスト名（ラベル）を設定する（[Chapter 3. The system initialization](https://www.debian.org/doc/manuals/debian-reference/ch03.en.html#_the_hostname)）

```
sudo emacs /etc/hostname
```

```
example
```

```
sudo emacs /etc/hosts
```

```
127.0.0.1	localhost
203.0.113.123	example.org	example

# The following lines are desirable for IPv6 capable hosts
::1     localhost ip6-localhost ip6-loopback
ff02::1 ip6-allnodes
ff02::2 ip6-allrouters
```

- 上記の設定ファイルを書き換えたらサーバを再起動する

```
sudo reboot
```

- 数秒後に再起動が完了するので再度 ssh で接続して設定が反映されているかを確認する
    - ログインシェルの冒頭の `yourname@example:~$` のような部分を見れば hostname が確認できる

## #13. sshd_config の設定をする

- PermitRootLogin を no に変更する
- AuthorizedKeysFile の行をアンコメントする

```
sudo emacs /etc/ssh/sshd_config
```

```
 # Authentication:
 LoginGraceTime 120
-PermitRootLogin prohibit-password
+PermitRootLogin no
 StrictModes yes

 RSAAuthentication yes
 PubkeyAuthentication yes
 #AuthorizedKeysFile    %h/.ssh/authorized_keys
+AuthorizedKeysFile     %h/.ssh/authorized_keys
```

- その後、設定ファイルを読み込み直す

```
sudo systemctl restart ssh
```

### #13-1. 公開鍵認証を可能にする

クライアントマシン側で生成した公開鍵を `~/.ssh/authorized_keys` に設置すれば公開鍵認証が可能になる。

クライアント側では次のようなコマンドで秘密鍵と公開鍵を生成できる。

```
ssh-keygen -t ed25519 -C 'yourname@example.org'
```

RSA 2048 bit 鍵でもよいが、Ed25519 鍵が使える環境であれば Ed25519 鍵を使いたい。

クライアント側では `~/.ssh/id_rsa` や `~/.ssh/id_ed25519` などがあれば自動的にそれが使われるが、明示的に使いたい場合は `ssh -i /path/to/id_ed25519` のようにオプションを付与して秘密鍵のパスを指定するか、次のように `~/.ssh/config` ファイルを記述すればよい。

```
Host example.org
    HostName        203.0.113.123
    Port            22
    User            yourname
    IdentityFile    ~/.ssh/example_id_ed25519
```

## #14. ファイアウォールを有効にする

```
sudo ufw status numbered
sudo ufw default deny
sudo ufw allow ftp
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw enable
sudo ufw status numbered
```

- `sudo ufw status numbered` で開放中のポートのルールを（ルール番号付きで）確認できる
- ルールを削除したい場合
    - `sudo ufw delete allow http` で allow http のルールを削除できる
    - `sudo ufw delete 123` でルール番号 123 のルールを削除できる
- 何らかの変更を加えた後は `$ sudo ufw reload` で変更を反映する

## #15. ミドルウェアをインストールする

```
sudo apt update

sudo apt install apache2
sudo apt install mysql-server-5.7
```

```
sudo apt install php7.0

The following NEW packages will be installed:
  php-common{a} php7.0 php7.0-cli{a} php7.0-common{a}
  php7.0-fpm{a} php7.0-json{a} php7.0-opcache{a} php7.0-readline{a}
```

```
sudo apt search php7.0

sudo apt install libapache2-mod-php7.0
sudo apt install php7.0-mysql
sudo apt install php7.0-curl
sudo apt install php7.0-gd
sudo apt install php7.0-mbstring
sudo apt install php7.0-xml
sudo apt install php7.0-intl

sudo service apache2 restart
```

### #15-1. その他のツールやコマンドをインストールする

```
sudo apt install software-properties-common
sudo apt install git
sudo apt install emacs
sudo apt install vim
sudo apt install language-pack-ja manpages-ja
```

```
sudo apt install silversearcher-ag
sudo apt install curl
sudo apt install tree
sudo apt install imagemagick
sudo apt install zip
sudo apt install zsh
sudo apt install gcc g++
```

### #15-2. Ruby をインストールする

```
sudo apt install rbenv ruby-build
```

- `~/.profile` に `eval "$(rbenv init -)"` を追記する

```
eval "$(rbenv init -)"
```

- `~/.profile` を再読込するためにシェルにログインし直す
- すると `~/.rbenv/` が生成される

```
$ rbenv version
system (set by /home/yourname/.rbenv/version)

$ ruby --version
ruby 2.3.1p112 (2016-04-26) [x86_64-linux-gnu]

$ gem --version
2.5.1
```

- rbenv でバージョンを管理する場合は `rbenv install x.x.x` を実行した後で `rbenv global x.x.x` を実行する
- ruby のインストール状況や rbenv でインストール可能なバージョンは次のコマンドで確認できる

```
gem env
rbenv install --list
```

### #15-3. Python をインストールする

- python3 はデフォルトでインストールされている
- `python-letsencrypt-apache` をインストールすると python (python2) がインストールされる

## #16. php.ini の設定をする

- `/etc/php/7.0/apache2/php.ini`

```
 ; max_input_vars = 1000
+max_input_vars = 1000000 ;;changed

 ; http://php.net/memory-limit
-memory_limit = 128M
+;; memory_limit = 128M
+memory_limit = 512M ;;changed

 ; http://php.net/post-max-size
-post_max_size = 8M
+;; post_max_size = 8M
+post_max_size = 128M ;;changed

 ; http://php.net/upload-max-filesize
-upload_max_filesize = 2M
+;; upload_max_filesize = 2M
+upload_max_filesize = 128M ;;changed

 ; Maximum number of files that can be uploaded via a single request
-max_file_uploads = 20
+;; max_file_uploads = 20
+max_file_uploads = 128 ;;changed

 ; http://php.net/date.timezone
 ;date.timezone =
+date.timezone = Asia/Tokyo ;;changed
```

## #17. MySQL の設定をする

- インストール直後

```
mysql> show variables like 'char%';
+--------------------------+----------------------------+
| Variable_name            | Value                      |
+--------------------------+----------------------------+
| character_set_client     | utf8                       |
| character_set_connection | utf8                       |
| character_set_database   | latin1                     |
| character_set_filesystem | binary                     |
| character_set_results    | utf8                       |
| character_set_server     | latin1                     |
| character_set_system     | utf8                       |
| character_sets_dir       | /usr/share/mysql/charsets/ |
+--------------------------+----------------------------+
8 rows in set (0.01 sec)
```

MySQL における utf8 は一般的な UTF-8（1〜4 バイトを扱える）とは意味が異なる。MySQL において utf8 では 1〜3 バイトで 1 文字を表現し、4 バイトでの表現は範囲外となる。これに対して MySQL では 1〜4 バイトでの表現を扱える utf8mb4 が MySQL 5.5.3 から使えるようになった。

- https://dev.mysql.com/doc/refman/5.5/en/charset-unicode-utf8mb4.html

> 10.1.9.3 The utf8mb4 Character Set (4-Byte UTF-8 Unicode Encoding)
>
> The character set named utf8 uses a maximum of three bytes per character and contains only BMP characters. As of MySQL 5.5.3, the utf8mb4 character set uses a maximum of four bytes per character supports supplementary characters:

- MySQL の Charset には utf8mb4 を設定する
- Collation（照合順序）には utf8mb4_bin を設定する

`/etc/mysql/conf.d/mysql.cnf` の差分

```
+[client]
+loose-default-character-set = utf8mb4
+
 [mysql]

```

`/etc/mysql/mysql.conf.d/mysqld.cnf` の差分

```
 #
 # * Basic Settings
 #
+innodb_file_format = Barracuda
+innodb_file_per_table = 1
+innodb_large_prefix
+character-set-server = utf8mb4
+collation-server = utf8mb4_bin
 user           = mysql
 pid-file       = /var/run/mysqld/mysqld.pid
 socket         = /var/run/mysqld/mysqld.sock
```

- その後、MySQL を再起動する

```
sudo service mysql restart
```

```
mysql> show variables like 'char%';
+--------------------------+----------------------------+
| Variable_name            | Value                      |
+--------------------------+----------------------------+
| character_set_client     | utf8mb4                    |
| character_set_connection | utf8mb4                    |
| character_set_database   | utf8mb4                    |
| character_set_filesystem | binary                     |
| character_set_results    | utf8mb4                    |
| character_set_server     | utf8mb4                    |
| character_set_system     | utf8                       |
| character_sets_dir       | /usr/share/mysql/charsets/ |
+--------------------------+----------------------------+
8 rows in set (0.00 sec)
```

- NOTE: [MySQL補足情報](./note/mysql.md)

### #17-1. MySQL 8.x を導入する

- https://dev.mysql.com/doc/mysql-apt-repo-quick-guide/en/

最新版のパッケージ URL は上記のページから辿れるダウンロードページを参照する。

```
wget https://dev.mysql.com/get/mysql-apt-config_0.8.10-1_all.deb
sudo dpkg -i mysql-apt-config_0.8.10-1_all.deb
sudo apt update
sudo apt install mysql-server
```

MySQL 8.0 ではデフォルトの文字セットが latin1 から utf8mb4 に変更された。

- https://dev.mysql.com/doc/relnotes/mysql/8.0/en/news-8-0-1.html#mysqld-8-0-1-charset
    - The default value of the character_set_server and character_set_database system variables has changed from `latin1` to `utf8mb4`.
    - The default value of the collation_server and collation_database system variables has changed from `latin1_swedish_ci` to `utf8mb4_0900_ai_ci`.
- デフォルトの照合順序 `utf8mb4_0900_ai_ci` は日本語を扱う場合に `utf8mb4_unicode_ci` と同様の問題が生じるため、`utf8mb4_ja_0900_as_cs` または `utf8mb4_bin` などを `collation-server` の項目で設定するとよい。
    - `0900` は "The collation is based on Unicode Collation Algorithm (UCA) 9.0.0 ..." と書かれている通りの意味で付けられている。

## #18. MySQL 一般ユーザを作成する

```
# ユーザを作成する
mysql> CREATE USER mysqluser@localhost IDENTIFIED by 'thepassword';
```

- ユーザを作成した後は権限を与える

```
# 権限を与える
mysql> GRANT ALL PRIVILEGES ON *.* TO mysqluser@localhost;

# 権限を外す
mysql> REVOKE ALL PRIVILEGES ON *.* FROM mysqluser@localhost;

# 特定の DB のみに権限を与える場合
# この例では proj_ を接頭辞に持ち、その後ろに少なくとも 1 文字以上を持つ DB への権限が与えられる
# LIKE 句と同様に、ワイルドカード _ は任意の1文字であり % は任意の 0 文字以上の意味を持つ
mysql> GRANT ALL PRIVILEGES ON `proj\__%`.* TO mysqluser@localhost;
```

- 存在するユーザの権限は次のようにして確認できる

```
mysql> SELECT Host, User FROM mysql.user;
mysql> SELECT * FROM mysql.user\G
mysql> SELECT * FROM mysql.db\G
```

## #19. Web 公開用ディレクトリを作成する

```
cd /var/www/
sudo mkdir hosts/
cd hosts/
```

```
# gweb グループを作成
sudo groupadd gweb
# 自身を gweb に追加
sudo gpasswd -a yourname gweb
# www-data (Apache) を gweb に追加
sudo gpasswd -a www-data gweb
```

```
cd /var/www/hosts/
sudo mkdir web/

# web/ のグループを gweb に変更する
sudo chgrp gweb web/
# グループに書き込み権限を与える
sudo chmod g+w web/
# グループに setgid を与える
sudo chmod g+s web/
```

- 自身を gweb グループに追加した設定を有効にするためにシェルにログインし直す

```
cd /var/www/hosts/
mkdir -p www/public_html/
emacs www/public_html/index.html
```

## #20. OS アカウントのパスワードポリシーを設定する

- `/etc/pam.d/common-password` の設定を確認する
- NOTE: [ユーザアカウントについて](./note/user_account.md)

```
# here are the per-package modules (the "Primary" block)
password	[success=1 default=ignore]	pam_unix.so obscure sha512
# here's the fallback if no module succeeds
password	requisite			pam_deny.so
# prime the stack with a positive return value if there isn't one already;
# this avoids us returning an error just because nothing sets a success code
# since the modules above will each just jump around
password	required			pam_permit.so
# and here are more per-package modules (the "Additional" block)
# end of pam-auth-update config
```

- このファイルで細かい設定を行うために pam_cracklib ライブラリ (libpam-cracklib) をインストールする

```
sudo apt install libpam-cracklib
```

- libpam-cracklib をインストールすると `/etc/pam.d/common-password` が書き換わる

```
 # here are the per-package modules (the "Primary" block)
-password       [success=1 default=ignore]      pam_unix.so obscure sha512
+password       requisite                       pam_cracklib.so retry=3 minlen=8 difok=3
+password       [success=1 default=ignore]      pam_unix.so obscure use_authtok try_first_pass sha512
```

この状態でも辞書にあるフレーズが設定できないなど多少は設定できるパスワードを制限できるが、このままでは最低 4 文字のパスワードを設定することが可能である。ここでは、次のように変更しておく。

- difok を 1 にして、1 文字違いのパスワードを設定できるようにする
- dcredit, ucredit, lcredit, ocredit を 0 以下にして、minlen 未満のパスワードを設定できないようにする
- minlen を 10 にして、最低でも 10 文字の長さを求めるようにする

```
 # here are the per-package modules (the "Primary" block)
-password       requisite                       pam_cracklib.so retry=3 minlen=8 difok=3
+password       requisite                       pam_cracklib.so retry=3 minlen=10 difok=1 dcredit=0 ucredit=0 lcredit=0 ocredit=0
 password       [success=1 default=ignore]      pam_unix.so obscure use_authtok try_first_pass sha512
```

## #21. Apache の設定をする

- NOTE: [Apache補足情報](./note/apache.md)

ここでは以下の内容について作業する。

- モジュールの有効化
- security.conf の設定
- dir.conf の設定
- envvars の設定

設定が完了したら Apache を再起動する。

```
sudo service apache2 restart
```

### #21-1. モジュールの有効化

- 現在有効なモジュールの確認

```
sudo apache2ctl -M
```

- モジュールを有効化する

```
sudo a2enmod rewrite
sudo a2enmod vhost_alias
sudo a2enmod authz_groupfile
sudo a2enmod headers
sudo a2enmod include
sudo a2enmod expires
sudo a2enmod ssl
```

### #21-2. security.conf の設定

`/etc/apache2/conf-available/security.conf` の差分

```
 # Set to one of:  Full | OS | Minimal | Minor | Major | Prod
 # where Full conveys the most information, and Prod the least.
 #ServerTokens Minimal
-ServerTokens OS
+#ServerTokens OS
 #ServerTokens Full
+ServerTokens Prod

 # Set to "EMail" to also include a mailto: link to the ServerAdmin.
 # Set to one of:  On | Off | EMail
-#ServerSignature Off
-ServerSignature On
+ServerSignature Off
+#ServerSignature On
```

### #21-3. dir.conf の設定

`/etc/apache2/mods-enabled/dir.conf` の差分

```
 <IfModule mod_dir.c>
-       DirectoryIndex index.html index.cgi index.pl index.php index.xhtml index.htm
+       DirectoryIndex index.php index.html
 </IfModule>
```

### #21-4. envvars の設定

- デフォルトでは LANG=C が適用される
- OS のロケールを Apache に引き継ぐ場合は `#. /etc/default/locale` の行をアンコメントする
- 今回は、OS のロケールは en_US.UTF-8 のままにしておき、Apache のみ ja_JP.UTF-8 を使うように設定しておく

`/etc/apache2/envvars` の差分

```
-export LANG=C
+# export LANG=C
+export LANG=ja_JP.UTF-8
 ## Uncomment the following line to use the system default locale instead:
 #. /etc/default/locale

 export LANG
```

- 末尾に umask を追記する

```
+
+# CUSTOMIZE: set 002 to permit g+w for setgid directory
+umask 002
```

## #22. httpd.conf の設定をする - HTTP 篇

- log.conf 用の cronolog をインストールする

```
sudo apt install cronolog
```

### #22-1. /etc/apache2/includes/ のファイルを作成する

```
cd /etc/apache2/
sudo mkdir includes/
cd includes/
```

#### hsts.conf

```
  # Enable this if your want HSTS (recommended)
  # * 15768000 = 60 * 60 * 24 * 365 / 2 (= 0.5 year)
  Header always set Strict-Transport-Security "max-age=15768000"
```

#### hsts_zero.conf

```
  # Enable this if your want HSTS (recommended)
  # * 15768000 = 60 * 60 * 24 * 365 / 2 (= 0.5 year)
  Header always set Strict-Transport-Security "max-age=0"
```

#### http2https.conf

```
  <Directory /var/www/hosts/web>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R,L]
  </Directory>
```

- .htaccess で `mod_rewrite` を用いると、親ディレクトリ内の .htaccess や https.conf 側での `mod_rewrite` の設定が無視されてしまう
- これを意図した通りに適用するには .htaccess 側で `RewriteOptions` ディレクティブを用いればよい（[Apache Module mod_rewrite](https://httpd.apache.org/docs/current/mod/mod_rewrite.html#rewriteoptions)）
- あるいは、ホスト名を直接記述する必要があるが `Redirect` ディレクティブを用いてもよい
    - `<VirtualHost *:80>` ディレクティブ内に以下を追記する

```
Redirect / https://example.org/
```

#### http.conf

```
  UseCanonicalName Off
```

#### https.conf

```
  UseCanonicalName Off
  # refs. https://wiki.mozilla.org/Security/Server_Side_TLS
  # refs. https://mozilla.github.io/server-side-tls/ssl-config-generator/
  SSLEngine on
  SSLCertificateFile    /etc/ssl/certs/ssl-cert-snakeoil.pem
  SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key
  # SSLCertificateFile      /etc/ssl/ssl-cert.pem
  # SSLCertificateKeyFile   /etc/ssl/ssl-privkey.pem
  # SSLCertificateChainFile /etc/ssl/ssl-chain.pem
  # Intermediate configuration, tweak to your needs
  SSLProtocol All -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
  # SSLCipherSuite FIPS@STRENGTH:!aNULL:!eNULL
  SSLCipherSuite ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256
  SSLHonorCipherOrder on
  SSLCompression off
  # 'SSLSessionTickets' directive (Available in httpd 2.4.11 and later)
  SSLSessionTickets off
  # OCSP Stapling, only in httpd 2.3.3 and later
  SSLUseStapling on
  SSLStaplingResponderTimeout 5
  SSLStaplingReturnResponderErrors off
  # Enable this if your want HSTS (recommended)
  # * 15768000 = 60 * 60 * 24 * 365 / 2 (= 0.5 year)
  # Header always set Strict-Transport-Security "max-age=15768000"
  # * To disable HSTS. We are using not only HTTPS but HTTP.
```

#### log.conf

```
  LogLevel warn
  # ErrorLog ${APACHE_LOG_DIR}/error.log
  # CustomLog ${APACHE_LOG_DIR}/access.log ltsv
  ErrorLog "| /usr/bin/cronolog /var/log/apache2/logs/error_%Y_%m.log"
  CustomLog "| /usr/bin/cronolog /var/log/apache2/logs/access_%Y_%m.log" ltsv
  LogFormat "host:%h\ttime:%t\treq:%r\tstatus:%>s\tsize:%b\treferer:%{Referer}i\tua:%{User-Agent}i\ttaken:%D\tisbot:%{Isbot}e\tdos:%{SuspectDoS}e\tharddos:%{SuspectHardDoS}ecache:%{X-Cache}o" ltsv_legacy
  LogFormat "opt_time:%{%Y/%m/%d %H:%M:%S}t\tserver:%A\tdomain:%V\tpath:%U%q\thttps:%{HTTPS}e\tmethod:%m\tstatus:%>s\tprotocol:%H\tuser:%u\tident:%l\tsize:%b\tresponse_time:%D\tcookie:%{cookie}i\tset_cookie:%{Set-Cookie}o\ttime:%{%d/%b/%Y:%H:%M:%S %z}t\treferer:%{Referer}i\tagent:%{User-Agent}i\thost:%h" ltsv
```

### #22-2. httpd.conf を記述する

```
cd /etc/apache2/

sudo a2dissite 000-default
sudo emacs /etc/apache2/sites-available/001-original.conf
```

```
############################################################
# default rule (for IP or default host request)
############################################################
<VirtualHost *:80>
  ServerName 203.0.113.123
  ServerAlias xxxxx.sakura.ne.jp
  DocumentRoot /var/www/hosts/web/www/public_html
  Include includes/http.conf
  Include includes/log.conf
  <Location />
    Require all denied
  </Location>
</VirtualHost>

<VirtualHost *:443>
  ServerName 203.0.113.123
  ServerAlias xxxxx.sakura.ne.jp
  DocumentRoot /var/www/hosts/web/www/public_html
  Include includes/https.conf
  Include includes/log.conf
  <Location />
    Require all denied
  </Location>
</VirtualHost>

############################################################
# ssl.example.org
############################################################
<VirtualHost *:80>
  ServerName ssl.example.org
  VirtualDocumentRoot /var/www/hosts/web/%-3+/public_html
  Include includes/http.conf
  Include includes/log.conf
  Include includes/http2https.conf
</VirtualHost>

<VirtualHost *:443>
  ServerName ssl.example.org
  VirtualDocumentRoot /var/www/hosts/web/%-3+/public_html
  Include includes/https.conf
  Include includes/hsts.conf
  Include includes/log.conf
</VirtualHost>

############################################################
# *.example.org
############################################################
<VirtualHost *:80>
  ServerName www.example.org
  ServerAlias *.example.org
  VirtualDocumentRoot /var/www/hosts/web/%-3+/public_html
  Include includes/http.conf
  Include includes/log.conf
</VirtualHost>

<VirtualHost *:443>
  ServerName www.example.org
  ServerAlias *.example.org
  VirtualDocumentRoot /var/www/hosts/web/%-3+/public_html
  Include includes/https.conf
  Include includes/log.conf
</VirtualHost>

############################################################
# example.org
############################################################
<VirtualHost *:80>
  ServerName example.org
  VirtualDocumentRoot /var/www/hosts/web/www/public_html
  Include includes/http.conf
  Include includes/log.conf
</VirtualHost>

<VirtualHost *:443>
  ServerName example.org
  VirtualDocumentRoot /var/www/hosts/web/www/public_html
  Include includes/https.conf
  Include includes/log.conf
</VirtualHost>

############################################################
# others
############################################################

# SSLStaplingCache
SSLStaplingCache shmcb:/var/run/ocsp(128000)

# Access denied to all directories (/var/www)
<Directory /var/www>
  Options FollowSymLinks
  AllowOverride None
  Require all denied
  # Access denied to dot-files such as .git/*
  RedirectMatch 403 /\.
</Directory>

# Access granted to web directory (/var/www/hosts/web)
<Directory /var/www/hosts/web>
  Require all granted
  # 403 Forbidden for non-existing sub-domains.
  RewriteEngine On
  RewriteCond %{DOCUMENT_ROOT} !-d
  RewriteRule ^ - [F]
</Directory>

<Directory /var/www/hosts>
  Options FollowSymLinks
  AllowOverride All
</Directory>
```

```
sudo a2ensite 001-original
sudo apache2ctl configtest
sudo service apache2 restart
```

### #22-3. .htaccess への記述

- Basic 認証

```
# authAccess
#   NOTE: Execute the following command to create .htpasswd
#     $ htpasswd -c .htpasswd username
AuthUserFile /var/www/hosts/web/DIR_NAME/.htpasswd
AuthName "Authorization Required"
AuthType Basic
Require valid-user
```

## #23. Let's Encrypt を用いて SSL 証明書を生成する

- https://letsencrypt.jp/usage/

```
cd /etc/apache2/sites-available/
sudo rm 000-default.conf default-ssl.conf
```

```
sudo add-apt-repository ppa:certbot/certbot
sudo apt update
sudo apt install certbot
```

PPA (Personal Package Archives) を追加した場合、公開鍵が有効期限切れなどで利用できないと `apt update` 中に GPG error が発生することがある。

```
W: GPG error: http://ppa.launchpad.net/certbot/certbot/ubuntu xenial InRelease: The following signatures couldn't be verified because the public key is not available: NO_PUBKEY 8C47BE8E75BCA694
```

この場合は、以下のようなコマンドで公開鍵を更新する。

```
sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 8C47BE8E75BCA694
```


```
sudo certbot certonly --apache -d example.org -d www.example.org -d ssl.example.org

# ワイルドカード証明書の場合
sudo certbot certonly --manual -d 'example.org' -d '*.example.org' -m 'admin@example.org' --manual-public-ip-logging-ok --agree-tos --preferred-challenges dns-01 --server https://acme-v02.api.letsencrypt.org/directory
# コマンド実行後に表示される ACME Challenge 文字列を _acme-challenge.example.org の TXT RR に設定する（TTL は 60 にしておく）
```

```
# 更新
certbot renew --apache --force-renew
```

- /etc/letsencrypt/ を git 管理対象外にする

```
sudo emacs /etc/.gitignore
```

```
.gitignore
/letsencrypt/
```

- シンボリックリンクを張る

```
sudo ln -nfs /etc/letsencrypt/live/example.org/cert.pem /etc/ssl/ssl-cert.pem
sudo ln -nfs /etc/letsencrypt/live/example.org/chain.pem /etc/ssl/ssl-chain.pem
sudo ln -nfs /etc/letsencrypt/live/example.org/fullchain.pem /etc/ssl/ssl-fullchain.pem
sudo ln -nfs /etc/letsencrypt/live/example.org/privkey.pem /etc/ssl/ssl-privkey.pem
```

## #24. httpd.conf の設定をする - HTTPS 篇

`/etc/apache2/includes/https.conf` の差分

```
   # refs. https://wiki.mozilla.org/Security/Server_Side_TLS
   # refs. https://mozilla.github.io/server-side-tls/ssl-config-generator/
   SSLEngine on
-  SSLCertificateFile    /etc/ssl/certs/ssl-cert-snakeoil.pem
-  SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key
-  # SSLCertificateFile      /etc/ssl/ssl-cert.pem
-  # SSLCertificateKeyFile   /etc/ssl/ssl-privkey.pem
-  # SSLCertificateChainFile /etc/ssl/ssl-chain.pem
+  # SSLCertificateFile    /etc/ssl/certs/ssl-cert-snakeoil.pem
+  # SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key
+  SSLCertificateFile      /etc/ssl/ssl-cert.pem
+  SSLCertificateKeyFile   /etc/ssl/ssl-privkey.pem
+  SSLCertificateChainFile /etc/ssl/ssl-chain.pem
   # Intermediate configuration, tweak to your needs
   SSLProtocol All -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
```

```
sudo apachectl configtest
sudo service apache2 restart
```

- 期待通りの SSL 証明書であることが確認できたら、letsencrypt の自動生成を設定する

```
cd /etc/ssl
```

```
sudo emacs /etc/ssl/update_letsencrypt.sh
```

```
#!/bin/sh

certbot renew --apache --force-renew
```

```
sudo emacs /etc/ssl/restart_ssl_services.sh
```

```
#!/bin/sh

service apache2 restart
service vsftpd restart
service postfix restart
```

```
sudo emacs /etc/ssl/update_letsencrypt_and_postprocess.sh
```

```
#!/bin/sh

/etc/ssl/update_letsencrypt.sh
/etc/ssl/restart_ssl_services.sh
```

```
sudo chmod 755 update_letsencrypt.sh update_letsencrypt_and_postprocess.sh
```

- crontab に追記する

`/etc/crontab` の差分

```
+
+# Update sslcert (Let's Encrypt)
+10 6   1 * *   root    /etc/ssl/update_letsencrypt_and_postprocess.sh
```

## #25. FTP を設定する

- OS ユーザアカウントを作成する
- NOTE: [ユーザアカウントについて](./note/user_account.md)

```
# ユーザを新規作成し、ホームディレクトリも生成する
sudo adduser yourname2nd

# gweb に追加する
sudo gpasswd -a yourname2nd gweb
```

- vsftpd をインストールする

```
sudo apt install vsftpd
sudo service vsftpd stop
sudo emacs /etc/vsftpd.conf
```

```
 #
 # Run standalone?  vsftpd can run either from an inetd or as a standalone
 # daemon started from an initscript.
-listen=NO
+listen=YES
 #
 # This directive enables listening on IPv6 sockets. By default, listening
 # on the IPv6 "any" address (::) will accept connections from both IPv6
@@ -19,7 +19,7 @@ listen=NO
 # sockets. If you want that (perhaps because you want to listen on specific
 # addresses) then you must run two copies of vsftpd with two configuration
 # files.
-listen_ipv6=YES
+#listen_ipv6=YES
 #
 # Allow anonymous FTP? (Disabled by default).
 anonymous_enable=NO
@@ -28,11 +28,11 @@ anonymous_enable=NO
 local_enable=YES
 #
 # Uncomment this to enable any form of FTP write command.
-#write_enable=YES
+write_enable=YES
 #
 # Default umask for local users is 077. You may wish to change this to 022,
 # if your users expect that (022 is used by most other ftpd's)
-#local_umask=022
+local_umask=002
 #
 # Uncomment this to allow the anonymous FTP user to upload files. This only
 # has an effect if the above global write enable is activated. Also, you will
@@ -96,8 +96,8 @@ connect_from_port_20=YES
 # predicted this attack and has always been safe, reporting the size of the
 # raw file.
 # ASCII mangling is a horrible feature of the protocol.
-#ascii_upload_enable=YES
-#ascii_download_enable=YES
+ascii_upload_enable=YES
+ascii_download_enable=YES
 #
 # You may fully customise the login banner string:
 #ftpd_banner=Welcome to blah FTP service.
@@ -119,16 +119,16 @@ connect_from_port_20=YES
 # (Warning! chroot'ing can be very dangerous. If using chroot, make sure that
 # the user does not have write access to the top level directory within the
 # chroot)
-#chroot_local_user=YES
-#chroot_list_enable=YES
+chroot_local_user=YES
+chroot_list_enable=YES
 # (default follows)
-#chroot_list_file=/etc/vsftpd.chroot_list
+chroot_list_file=/etc/vsftpd.chroot_list
 #
 # You may activate the "-R" option to the builtin ls. This is disabled by
 # default to avoid remote users being able to cause excessive I/O on large
 # sites. However, some broken FTP clients such as "ncftp" and "mirror" assume
 # the presence of the "-R" option, so there is a strong case for enabling it.
-#ls_recurse_enable=YES
+ls_recurse_enable=YES
 #
 # Customization
 #
@@ -146,9 +146,24 @@ pam_service_name=vsftpd
 #
 # This option specifies the location of the RSA certificate to use for SSL
 # encrypted connections.
-rsa_cert_file=/etc/ssl/certs/ssl-cert-snakeoil.pem
-rsa_private_key_file=/etc/ssl/private/ssl-cert-snakeoil.key
-ssl_enable=NO
+#rsa_cert_file=/etc/ssl/certs/ssl-cert-snakeoil.pem
+#rsa_private_key_file=/etc/ssl/private/ssl-cert-snakeoil.key
+rsa_cert_file=/etc/ssl/ssl-cert.pem
+rsa_private_key_file=/etc/ssl/ssl-privkey.pem
+ssl_enable=YES
+force_local_data_ssl=YES
+force_local_logins_ssl=YES
+ssl_tlsv1=YES
+ssl_sslv2=NO
+ssl_sslv3=NO
+pasv_max_port=60099
+pasv_min_port=60000
+ssl_ciphers=HIGH
+require_ssl_reuse=NO
+seccomp_sandbox=NO
+allow_writeable_chroot=YES
+local_root=/var/www/hosts
+user_config_dir=/etc/vsftpd/vsftpd_user_conf

 #
 # Uncomment this to indicate that vsftpd use a utf8 filesystem.
```

```
sudo touch /etc/vsftpd.chroot_list
sudo mkdir -p /etc/vsftpd/vsftpd_user_conf
```

```
sudo ufw allow 60000:60099/tcp
sudo ufw reload

sudo service vsftpd restart
```

- NOTE: [FTP補足情報](./note/ftp.md)

### #25-1. SFTP を使用不可にする

`/ssh/sshd_config` の差分

```
-Subsystem sftp /usr/lib/openssh/sftp-server
+# Subsystem sftp /usr/lib/openssh/sftp-server
```

```
sudo systemctl restart ssh
```

### #25-2. SSH ログインを不可にする

`/ssh/sshd_config` の差分

```
+DenyUsers yourname2nd
```

```
sudo systemctl restart ssh
```

### #25-3. FTP ルートディレクトリを変更する

```
sudo emacs /etc/vsftpd/vsftpd_user_conf/yourname2nd
```

```
local_root=/path/to/
```

```
sudo service vsftpd restart
```

## #26. Postfix をインストールする

```
sudo apt install postfix
```

- インストール時の初期設定では Internet を選び、ドメイン名は取得したドメイン名 (example.org) を入力する

```
sudo ufw status numbered
sudo ufw allow smtp
sudo ufw reload
```

```
sudo emacs /etc/postfix/main.cf
```

```
 # is /etc/mailname.
 #myorigin = /etc/mailname

-smtpd_banner = $myhostname ESMTP $mail_name (Ubuntu)
+#smtpd_banner = $myhostname ESMTP $mail_name (Ubuntu)
+smtpd_banner = $myhostname ESMTP unknown
 biff = no

 # appending .domain is the MUA's job.
 append_dot_mydomain = no

 # Uncomment the next line to generate "delayed mail" warnings
 #delay_warning_time = 4h

 readme_directory = no

 # TLS parameters
-smtpd_tls_cert_file=/etc/ssl/certs/ssl-cert-snakeoil.pem
-smtpd_tls_key_file=/etc/ssl/private/ssl-cert-snakeoil.key
+#smtpd_tls_cert_file=/etc/ssl/certs/ssl-cert-snakeoil.pem
+#smtpd_tls_key_file=/etc/ssl/private/ssl-cert-snakeoil.key
+smtpd_tls_cert_file=/etc/ssl/ssl-cert.pem
+smtpd_tls_key_file=/etc/ssl/ssl-privkey.pem
+smtpd_tls_CAfile=/etc/ssl/ssl-chain.pem
 smtpd_use_tls=yes
 smtpd_tls_session_cache_database = btree:${data_directory}/smtpd_scache
 smtp_tls_session_cache_database = btree:${data_directory}/smtp_scache

 # See /usr/share/doc/postfix/TLS_README.gz in the postfix-doc package for
 # information on enabling SSL in the smtp client.

 smtpd_relay_restrictions = permit_mynetworks permit_sasl_authenticated defer_unauth_destination
 myhostname = example.org
 alias_maps = hash:/etc/aliases
 alias_database = hash:/etc/aliases
 myorigin = /etc/mailname
-mydestination = $myhostname, example.org, localhost.org, , localhost
+mydomain = example.org
+mydestination = $mydomain, localhost.$mydomain, localhost
 relayhost =
 mynetworks = 127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128
 mailbox_size_limit = 0
 recipient_delimiter = +
 inet_interfaces = all
 inet_protocols = all
+
+home_mailbox = Maildir/
+# 134217728 = 128(MB)*1024(KB)*1024(B)
+message_size_limit = 134217728
```

```
sudo service postfix restart
```

このホストからメールを送信した場合、その宛先ドメイン名が mydestination に記述されていれば、DNS の名前解決を行わずに自身のホストを送信先としてメールを送信する。

もし当該ドメイン名（ここでは example.org）の受信メールサーバが別に存在する場合に mydestination に example.org を記述してしまうと、このホストからの送信メールが期待する受信メールサーバに届かないことになる。

メール送信が失敗している場合には `/var/log/mail.log` のログを見ることでヒントを得ることができる。

### #26-1. mailutils をインストールする

```
sudo apt install mailutils
```

### #26-2. SMTP で TLS を使用する

- http://www.postfix.org/TLS_README.html
- `smtp_tls_security_level` を `may` に設定すると、送信先メールサーバが対応している場合に、このサーバ（メールクライアント）からメールを送信する際に TLS を用いる
- `smtpd_tls_security_level` を `may` に設定すると、送信元メールクライアントに、このサーバ（メールサーバ）が TLS に対応していることを通知する

`/etc/postfix/main.cf` の差分

```
 smtpd_tls_session_cache_database = btree:${data_directory}/smtpd_scache
 smtp_tls_session_cache_database = btree:${data_directory}/smtp_scache

+smtp_tls_CAfile = /etc/ssl/ssl-chain.pem
+smtp_tls_security_level = may
+smtpd_tls_received_header = yes
+smtpd_tls_security_level = may
+tls_random_source = dev:/dev/urandom
+
 # See /usr/share/doc/postfix/TLS_README.gz in the postfix-doc package for
 # information on enabling SSL in the smtp client.
```

### #26-3. 受信メールを転送する

```
sudo emacs /etc/aliases
```

```
# See man 5 aliases for format
postmaster:    root
yourname:    yourname.example.org@gmail.com
```

```
sudo newaliases
```

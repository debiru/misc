# ユーザアカウントについて

## useradd と adduser の違い

ユーザを作成するコマンドには useradd と adduser がありますが、その違いは次のようになっています。

* useradd コマンド
    * ユーザを作成する（ホームディレクトリは作成しない）
    * -m オプションを付けた場合はホームディレクトリも作成する
    * ただし、/etc/login.defs の設定などに CREATE_HOME yes の記述がある場合は、-m オプションを省略してもホームディレクトリが作成される
* adduser コマンド
    * CentOS系では、存在しないか、adduser へのシンボリックリンクとなっている
        * CentOS系では /etc/login.defs に CREATE_HOME yes が記述されている
    * Debian系では、useradd のラッパーコマンドとして実装されている
        * useradd -m 相当の処理が行われる
* ホームディレクトリを作成したい場合は adduser を使えばオプションを気にする必要がない。

通常のユーザを作成したい場合（ホームディレクトリを作成したい場合）は、adduser を使うようにします。

### CentOS系 (CentOS 6) の adduser

```
lrwxrwxrwx. 1 root root      7  2月 17 14:56 2015 /usr/sbin/adduser -> useradd
-rwxr-x---. 1 root root 103096 10月 15 22:34 2014 /usr/sbin/useradd
```

```
# If useradd should create home directories for users by default
# On RH systems, we do. This option is overridden with the -m flag on
# useradd command line.
#
CREATE_HOME	yes
```

### Debian系 (Ubuntu 14.04) の adduser

```
-rwxr-xr-x 1 root root  35125 Nov  7  2013 /usr/sbin/adduser*
-rwxr-xr-x 1 root root 110456 Feb 17  2014 /usr/sbin/useradd*
```

## pam_cracklib の設定について

pam_cracklib ライブラリを用いる場合の設定項目については `$ man pam_cracklib` で確認できます。

直感に反しますが、パスワードの最低文字長については minlen 値だけでは指定できません。パスワードに設定可能な実際の最低文字長は、minlen の設定値に加えて、各文字種の credit 値によって決まります。

| credit | 文字種 |
| :---: | :---: |
| dcredit | digit: 数値 |
| ucredit | upper-case: 大文字 |
| lcredit | lower-case: 小文字 |
| ocredit | other: 記号類 |

ある文字種の credit 値が 0 以上の場合、パスワードにその文字種が含まれると、minlen からその credit 値が引かれます。その上でパスワード文字長が minlen 以上であればパスワードとして受け付けられます。

- minlen=10 で ocredit=2, 他の credit が 0 である場合
    - 最低 10 文字必要だが、記号が含まれている場合には最低 8 文字あればよい
- minlen=10 で他の credit が 1 である場合
    - 使用文字種が 1 種類であれば最低 9 文字、使用文字種が k 種類であれば最低 10-k 文字あればよい
    - credit 指定を省略している場合は、その credit はデフォルト値として 1 が設定される

ある文字種の credit 値が 0 未満の場合（負の場合）、その絶対値だけその文字種が含まれていないとパスワードとして受け付けられません。

- minlen=10 で ocredit=-2, 他の credit が 0 である場合
    - 最低 10 文字必要で、かつ記号が 2 文字以上含まれる必要がある
- minlen=10 で他の credit が -1 である場合
    - 最低 10 文字必要で、かつ全ての文字種が 1 文字以上ずつ含まれる必要がある

## SSH ログインを禁止する

### FTP ログインは許可する

FTP ログインは許可するが SSH ログインは禁止する場合は、`/etc/ssh/sshd_config` ファイルに次のような記述を追記することで可能になります。

```
DenyUsers yourguestname anotherusername
```

そして、ssh を再読み込みします。

```
$ sudo reload ssh
```

これで yourguestname ユーザと anotherusername ユーザは SSH ログインができなくなります。

DenyUsers とは別に AllowUsers という項目がありますが、AllowUsers を記述した場合は、記述されているユーザのみ SSH ログインを許可し、それ以外のユーザはログインが禁止されます。

### FTP ログインも禁止する

そのユーザアカウントでのログインを一切禁止したい場合には、アカウントを削除してもよいですが、アカウントは残したままパスワードロックすることができます。

パスワードロックは passwd コマンドを実行することで可能です。

```
# パスワードをロックする
$ sudo passwd -l yourguestname

# アカウントの状態を確認する
$ sudo passwd -S yourguestname

# パスワードをアンロックする
$ sudo passwd -u yourguestname
```

### passwd -S コマンドについて

`$ sudo passwd -S yourname` のようにコマンドを打つと、そのユーザアカウントの状態が次のように表示されます。

```
yourname P 05/17/2015 0 99999 7 -1
```

| フィールド | 値 | 説明 |
| :---: | :---: | --- |
| 1列目 | yourname | ユーザ名 |
| 2列目 | P | ステータスを表す記号<br />(L) パスワードがロックされている<br />(NP) パスワードを持っていない<br />(P) 使用可能なパスワードを持っている |
| 3列目 | 05/17/2015 | パスワードの最終更新日 |
| 4列目 | 0 | パスワードの変更禁止期間（残り日数） |
| 5列目 | 99999 | パスワードの有効期間（残り日数） |
| 6列目 | 7 | パスワードの有効期間がもうすぐ終わるという警告が表示される日数 |
| 7列目 | -1 | パスワードの有効期限（日付） |

パスワードロックを行うと 2 列目の値が変わります。

なお、アカウントにはパスワードの期限を設定できますが、この 5 列目の値（有効期間）と 7 列目の値（有効期限）は意味が異なります。

パスワードの有効期間というものは、何らかの理由で「パスワードの定期的変更」を強制するような場合に用いられます。運用上の都合で、共有アカウントを用いるような場合かもしれません。

このとき、パスワードの有効期限が切れると、ユーザはそのパスワードでログインすることができませんが、新しいパスワードを設定するように求められます。新しいパスワードを設定すればログインができるようになります。

そして、これとは別にパスワードの有効期限日を超えると、そのユーザは以後、ログインおよびパスワードの再設定をすることができなくなります。

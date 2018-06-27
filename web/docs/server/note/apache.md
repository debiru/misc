# Apache補足情報

## Apache 設定ファイルについて

sites-available および sites-enabled 内の設定ファイルを httpd.conf と呼んでいますが、それ以外には前述の security.conf や dir.conf があり、他にも /etc/apache2/apache2.conf があります。

apache2.conf はインストール時のままで内容を変更する必要は今のところありません。ただし、Apache が OS 上で何かを実行する際のユーザ名とグループ名を変更したい場合などは apache2.conf あるいは環境変数が定義されている /etc/apache2/envvars を適宜編集するなどしてください。

なお、デフォルトでは Apache のユーザ名とグループ名は www-data になっています。

```
export APACHE_RUN_USER=www-data
export APACHE_RUN_GROUP=www-data
```

「local_umask を 002 にするケース」で FTP での操作時に g+w が付くようにしていますが、Apache によってディレクトリやファイルが作成される場合に g+w が付くように umask を設定したい場合があります。この場合には /etc/apache2/envvars に umask を追記します。

```
 ## This will produce a verbose output on package installations of web server modules and web application
 ## installations which interact with Apache
 #export APACHE2_MAINTSCRIPT_DEBUG=1
+
+# CUSTOMIZE: set 002 to permit g+w for setgid directory
+umask 002
```

### サブドメインをディレクトリに関連付ける設定例

public_html のようなディレクトリを用意せず、/var/www/hosts/web/ 直下のディレクトリを DocumentRoot にするような想定で、それらのディレクトリ名をサブドメインとしてリクエストを受け付けるような設定例です。ここでは、必要がないため web ディレクトリの孫ディレクトリはサブドメインで直接アクセスできるようにはしていません。

| リクエスト URL | DocumentRoot |
| --- | --- |
| http://example.com/ | /var/www/hosts/web/ |
| http://example.com/foo/ | /var/www/hosts/web/foo/ |
| http://foo.example.com/ | /var/www/hosts/web/foo/ |
| http://foo.example.com/bar/ | /var/www/hosts/web/foo/bar/ |
| http://bar.foo.example.com/ | /var/www/hosts/web/bar.foo/ |

この対応は一例です。設定次第でリクエスト URL に対して対応させる DocumentRoot を好きなように設定できます。上記を実現する設定例は次のようになります。

```
# /etc/apache2/site-available/001-original.conf

<VirtualHost *:80>
  # default rule (for IP or default host request)
  DocumentRoot /var/www/hosts/web
  <Location />
    Require all denied
  </Location>
</VirtualHost>

<VirtualHost *:80>
  ServerAlias *.example.com
  VirtualDocumentRoot /var/www/hosts/web/%-3+
  Include includes/http.conf
</VirtualHost>

<VirtualHost *:80>
  ServerAlias example.com
  VirtualDocumentRoot /var/www/hosts/web
  Include includes/http.conf
</VirtualHost>

# Access denied to all directories (/var/www/*)
# You need to use "Require" directive. (public, authAccess, hostAccess)
#   # public
#   Require all granted
#
#   # authAccess
#   AuthUserFile /var/www/hosts/path/to/.htpasswd
#   AuthName "Authorization Required"
#   AuthType Basic
#   Require valid-user
#
#   # hostAccess
#   Require ip 192.0.2.123
<Directory /var/www>
  Options FollowSymLinks
  AllowOverride None
  Require all denied
</Directory>

<Directory /var/www/hosts/*>
  Options FollowSymLinks
  AllowOverride All
  # Access denied to dot-files such as .git/*
  RedirectMatch 403 /\.
</Directory>
```

```
# /etc/apache2/includes/http.conf

  UseCanonicalName Off
  ErrorLog ${APACHE_LOG_DIR}/error.log
  LogLevel warn
  CustomLog ${APACHE_LOG_DIR}/access.log combined
```

1つ目の VirtualHost ディレクティブでは、2つ目以降の ServerName (ServerAlias) にマッチしないドメイン名または IP アドレスでの接続に使われます。（VirtualHost ディレクティブは  ServerName (ServerAlias) にマッチしなかった場合、最初に書かれているルールが使われます。）

2つ目の VirtualHost ディレクティブでは、サブドメインでのリクエストをサブディレクトリに対応させています。

3つ目の VirtualHost ディレクティブでは、example.com へのリクエストを web ディレクトリに対応させています。

このように、リクエストされたドメイン名中の文字列を DocumentRoot 指定に含めるためには Directory Name Interpolation ( http://httpd.apache.org/docs/current/mod/mod_vhost_alias.html#interpol ) を用いていますが、2つ目と3つ目の指定を1つの VirtualHost ディレクティブで表現する方法が見つからなかったため分けて記述しています。

2つ目の VirtualHost ディレクティブ内の DocumentRoot について、`%-3+` はトップレベルドメイン（最右ドメイン）からみて、3つ目より左にマッチします。http://bar.foo.example.com/ であれば bar.foo が対応します。なお、もし取得したドメインが example.co.jp のようにラベル（ドットで区切られた example, co, jp）が3つの場合は、`%-4+` にしなければ期待通りの結果になりません（上記のように負値の Directory Name Interpolation を用いている場合）。

2つ目と3つ目では、ドメインと DocumenetRoot の指定以外は共通の記述なので、Include ディレクティブを用いて共通部分を外部ファイルに切り出しています。/etc/apache2/includes/ ディレクトリとその中のファイルは今回のために作成したインクルード用ファイルです。

## /var/www/hosts/ ディレクトリを作成し権限を与える

/var/www/hosts/ ディレクトリと gweb グループを作成し、ユーザに gweb グループを付与します。この文書では DocumentRoot に /var/www/hosts/ を使っていますが、VirtualHost を使う場合は /var/www/vhosts/ を用いる方が一般的かもしれません。

```
# /var/www/hosts/ ディレクトリを作成する
$ sudo mkdir /var/www/hosts

# gweb グループをつくる
$ sudo groupadd gweb

# ユーザに gweb グループを付与する
$ sudo gpasswd -a yourname gweb
```

以下に示すような設定を参考に、それらを組み合わせるなどして目的に合った環境を構築してください。

### ユーザごとにドメインを管理する場合の設定例

DocumentRoot で `%0` を用いて、hosts ディレクトリ直下にドメイン名のディレクトリを作成し、そのドメイン（サブドメイン）はそれを作成したユーザしか変更できないようにする設定例です。

```
<VirtualHost *:80>
  VirtualDocumentRoot /var/www/hosts/%0/public_html
</VirtualHost>
```

gweb グループに属するユーザであれば /var/www/hosts/ へ書き込めるようにし、その直下に作成したディレクトリ（ファイル）は作成者しか変更できないようにします。

```
# hosts ディレクトリのグループを gweb にして、グループに書き込み権限を与える
$ sudo chgrp gweb /var/www/hosts
$ sudo chmod g+w /var/www/hosts

# hosts ディレクトリに Sticky Bit を与える
$ sudo chmod +t /var/www/hosts
```
```
# directory permission
/var/www/hosts (drwxrwsr-t root gweb)
```

これで、ユーザ Alice が /var/www/hosts/alice.example.com/ を作成して自由にサイトを公開したり、ユーザ Bob が /var/www/hosts/bob.example.com/ や /var/www/hosts/test.example.com/ を作成したりするような運用が実現できます。

### /var/www/hosts/ 下を共有して管理する場合の設定例

ユーザ全員が FTP で /var/www/hosts/ 以下を管理して、FTP 操作権限があれば作成者によらず、ファイルの編集や削除が行えるようにする設定例です。

```
<VirtualHost *:80>
  ServerAlias *.example.com
  VirtualDocumentRoot /var/www/hosts/web/%-3+
</VirtualHost>

<VirtualHost *:80>
  ServerAlias example.com
  VirtualDocumentRoot /var/www/hosts/web
</VirtualHost>
```

gweb グループに属するユーザであれば /var/www/hosts/ の中身を自由に変更できるようにします。また、他人が作成したファイルであっても編集できるようにします。

```
# hosts ディレクトリのグループを gweb にして、グループに書き込み権限を与える
$ sudo chgrp gweb /var/www/hosts
$ sudo chmod g+w /var/www/hosts

# hosts ディレクトリに setgid 属性を与える
$ sudo chmod g+s /var/www/hosts

# DocumentRoot に合わせて web ディレクトリを作成する
$ mkdir /var/www/hosts/web
```
```
# directory permission
/var/www/hosts/ (drwxrwsr-x root gweb)
```

setgid 属性の付いたディレクトリ直下でファイルを新規作成した場合、新規作成されたファイルのグループが親ディレクトリのグループを継承します。ディレクトリを新規作成した場合も同様で、setgid 属性も継承されます。これにより、setgid 属性の付いたディレクトリ内に新規作成されたのファイルやディレクトリの全てに同じグループが設定され、そのグループに属するユーザがファイルを編集したりできる状態をつくることができます。

setgid 属性を用いたこの運用を行う場合は、FTP や Apache の設定で umask を（一般的には 022 ですが）002 にします。これにより、FTP 等での新規ファイル（ディレクトリ）作成時にそのファイルのグループに write 権限が付与され、setgid の機能を期待通り利用できます。

### ファイル（ディレクトリ）の変更権限の制御について

あるディレクトリ自体の変更を制限したり、その中のファイル（ディレクトリ）の変更を制限する方法について書いておきます。

あるファイルの編集（書き込み）を制限するにはそのファイル自体の write 権限を外せばよいですが、そのファイルの削除や移動を制限するには親ディレクトリの write 権限を外す必要があります。しかしこれでは、その親ディレクトリ内に新規ファイルを作成するなどの操作ができなくなってしまいます。

あるファイル（ディレクトリ）の削除や移動を制限するためには、親ディレクトリの権限を変更する必要がありますが、write 権限を付けたままこれを実現する方法が Sticky Bit でした。ディレクトリに対して Sticky Bit をセットすると、その直下のファイル（ディレクトリ）は「write 権限があれば新規作成はできるが、削除や移動は所有者しかできない」という状態になります。

- ファイル（ディレクトリ）のパーミッションを変更する（write 権限）
- 親ディレクトリのパーミッションを変更する（write 権限、Sticky Bit フラグ）

この他にも `$ chattr +i /path/to/filename` のように chattr コマンドで設定できる immutable 属性（不変属性）を用いれば、ファイルやディレクトリ単位で削除や移動および編集を制限できますが、ディレクトリに immutable 属性を与えた場合は、そのディレクトリ直下にファイルを作成したりすることが一切できなくなります。属性名の通り、そのディレクトリに対する一切の変更が制限されます。ただし、不変属性はディレクトリ自身および直下にのみ影響し、子ディレクトリ内については影響しません。なお、chattr で設定できる属性は lsattr コマンドで確認できます。

## HTTPS の設定に関して

```
  UseCanonicalName Off
  # refs. https://wiki.mozilla.org/Security/Server_Side_TLS
  SSLEngine on
  SSLCertificateFile      /etc/ssl/certs/ssl_cert.crt
  SSLCertificateKeyFile   /etc/ssl/private/ssl_cert.key
  SSLCertificateChainFile /etc/ssl/certs/sub.class1.server.ca.pem
  SSLCACertificateFile    /etc/ssl/certs/ca.pem
  # Intermediate configuration, tweak to your needs
  SSLProtocol All -SSLv2 -SSLv3
  SSLCipherSuite FIPS@STRENGTH:!aNULL:!eNULL
  SSLHonorCipherOrder on
  SSLCompression off
  # OCSP Stapling, only in httpd 2.3.3 and later
  SSLUseStapling on
  SSLStaplingResponderTimeout 5
  SSLStaplingReturnResponderErrors off
  # Enable this if your want HSTS (recommended)
  # * 15768000 = 60 * 60 * 24 * 365 / 2 (= 0.5 year)
  # Header always set Strict-Transport-Security "max-age=15768000"
  # * To disable HSTS. We are using not only HTTPS but HTTP.
```

Apache 2.4.8 では `SSLCertificateChainFile` ディレクティブは使うべきではないとされています。

Apache 2.4.8 以降で SSLCertificateChainFile を用いると configtest などの実行時に次のような警告が出力されます。

```
AH02559: The SSLCertificateChainFile directive (/etc/path/to/https.conf) is deprecated, SSLCertificateFile should be used instead
```

これについては、次の文書に書かれています。

- http://httpd.apache.org/docs/current/mod/mod_ssl.html#sslcertificatechainfile
- http://svn.apache.org/repos/asf/httpd/httpd/branches/2.4.x/CHANGES

```
# http://httpd.apache.org/docs/current/mod/mod_ssl.html#sslcertificatechainfile

SSLCertificateChainFile is deprecated

SSLCertificateChainFile became obsolete with version 2.4.8, when SSLCertificateFile was extended to also load intermediate CA certificates from the server certificate file.
```

- （拙訳）SSLCertificateChainFile は非推奨となっています。
- （拙訳）サーバ証明書のファイルから中間CA証明書も読み込めるように SSLCertificateFile が拡張されたことに伴って、SSLCertificateChainFile はバージョン 2.4.8 からそれに取って代わられます。

```
# http://svn.apache.org/repos/asf/httpd/httpd/branches/2.4.x/CHANGES

Changes with Apache 2.4.8

  *) mod_ssl: Remove the hardcoded algorithm-type dependency for the
     SSLCertificateFile and SSLCertificateKeyFile directives, to enable
     future algorithm agility, and deprecate the SSLCertificateChainFile
     directive (obsoleted by SSLCertificateFile). [Kaspar Brand]
```

- （拙訳）将来のアルゴリズムを軽快にするために、SSLCertificateFile ディレクティブと SSLCertificateKeyFile ディレクティブに対して、（SSLCertificateChainFile を）直に記述する方式に依存することを無くしました。SSLCertificateChainFile ディレクティブは使われなくなり、SSLCertificateFile に取って代わられます。

### SSL のセキュリティ強度について

SSL (HTTPS) が提供されているサイトのセキュリティ強度をチェックするために、以下の様なサービスが提供されています。

- https://sslcheck.globalsign.com/ja
- https://www.ssllabs.com/ssltest/

これらのサービスを用いると、上記で設定した SSL 関連の設定が適切かどうかを確認できます。細かくチェックされますが、結果は A, B, C のようなグレードで評価されます。最高評価は A+ です。

上記の設定では、グレード A が得られるようになっています。この状態で HTST を有効にすればグレード A+ を得ることができますが、この文書では、意図的に HTTP でもアクセスできるようにする環境を想定しているため HTST は無効にしています。

なお、HTST はレスポンスヘッダの情報であるため、チェックするドメイン名のルート URL へのリクエストに対してサーバが HTTP ステータスコード 4xx や 5xx を返す場合には HTST は有効になりません。

### SSLCipherSuite について

この文書では SSLCipherSuite に `FIPS@STRENGTH:!aNULL:!eNULL` を指定しています。

- FIPS@STRENGTH
- !aNULL：認証無しの通信を禁止
- !eNULL：暗号化無しの通信を禁止

この CipherSuite がどのように展開されるのかは `$ openssl ciphers -v 'FIPS@STRENGTH:!aNULL:!eNULL'` のようにコマンドを打てば確認できます。

また、以下の cipherscan を用いると SSL のセキュリティ強度チェックサービスのように結果を確認することができます。

- https://wiki.mozilla.org/Security/Server_Side_TLS#CipherScan
- https://github.com/jvehent/cipherscan

この文書の通りに環境を構築したサーバに対して cipherscan を行った結果は以下のようになりました。

```
Target: example.com:443

prio  ciphersuite                  protocols              pfs                 curves
1     ECDHE-RSA-AES256-GCM-SHA384  TLSv1.2                ECDH,P-256,256bits  prime256v1
2     ECDHE-RSA-AES256-SHA384      TLSv1.2                ECDH,P-256,256bits  prime256v1
3     ECDHE-RSA-AES256-SHA         TLSv1,TLSv1.1,TLSv1.2  ECDH,P-256,256bits  prime256v1
4     DHE-RSA-AES256-GCM-SHA384    TLSv1.2                DH,2048bits         None
5     DHE-RSA-AES256-SHA256        TLSv1.2                DH,2048bits         None
6     DHE-RSA-AES256-SHA           TLSv1,TLSv1.1,TLSv1.2  DH,2048bits         None
7     AES256-GCM-SHA384            TLSv1.2                None                None
8     AES256-SHA256                TLSv1.2                None                None
9     AES256-SHA                   TLSv1,TLSv1.1,TLSv1.2  None                None
10    ECDHE-RSA-DES-CBC3-SHA       TLSv1,TLSv1.1,TLSv1.2  ECDH,P-256,256bits  prime256v1
11    EDH-RSA-DES-CBC3-SHA         TLSv1,TLSv1.1,TLSv1.2  DH,2048bits         None
12    DES-CBC3-SHA                 TLSv1,TLSv1.1,TLSv1.2  None                None
13    ECDHE-RSA-AES128-GCM-SHA256  TLSv1.2                ECDH,P-256,256bits  prime256v1
14    ECDHE-RSA-AES128-SHA256      TLSv1.2                ECDH,P-256,256bits  prime256v1
15    ECDHE-RSA-AES128-SHA         TLSv1,TLSv1.1,TLSv1.2  ECDH,P-256,256bits  prime256v1
16    DHE-RSA-AES128-GCM-SHA256    TLSv1.2                DH,2048bits         None
17    DHE-RSA-AES128-SHA256        TLSv1.2                DH,2048bits         None
18    DHE-RSA-AES128-SHA           TLSv1,TLSv1.1,TLSv1.2  DH,2048bits         None
19    AES128-GCM-SHA256            TLSv1.2                None                None
20    AES128-SHA256                TLSv1.2                None                None
21    AES128-SHA                   TLSv1,TLSv1.1,TLSv1.2  None                None

Certificate: trusted, 2048 bit, sha256WithRSAEncryption signature
TLS ticket lifetime hint: 300
OCSP stapling: supported
Cipher ordering: server
```

## HTTP リクエストに対するアクセス制御について

Apache であれば .htaccess を用いて、ディレクトリごとにアクセス制御などの設定を行うことができます。.htaccess に書かれた内容を解釈する前に Apache の設定ファイル（apache2.conf や httpd.conf など）が解釈され、そこでは Directory ディレクティブや Location ディレクティブも記述できますが、これらの設定がマージされる順序は http://httpd.apache.org/docs/current/en/sections.html#merging に書かれています。

```
1. <Directory> (except regular expressions) and .htaccess done simultaneously
   (with .htaccess, if allowed, overriding <Directory>)
2. <DirectoryMatch> (and <Directory "~">)
3. <Files> and <FilesMatch> done simultaneously
4. <Location> and <LocationMatch> done simultaneously
5. <If>
```

### Order ディレクティブと Satisfy ディレクティブ

Order ディレクティブ http://httpd.apache.org/docs/current/en/mod/mod_access_compat.html#order を用いて IP などによる制限を行う場合は、次のように書きました。

```
Order Deny,Allow
Deny from all
Allow from 127.0.0.1
```

これは、127.0.0.1 からのアクセスのみを許可するような設定です。

ここで、Basic 認証など、認証による制限を組み合わせる場合、次のように書きました。

```
# authAccess
AuthUserFile /var/www/hosts/web/sandbox/.htpasswd
AuthName "Authorization Required"
AuthType Basic
Require valid-user

# hostAccess
Order Deny,Allow
Deny from all
Allow from 127.0.0.1

Satisfy Any
```

authAccess と hostAccess の条件があるような場合、これらを両方満たす必要があるか、一方さえ満たせばよいかを指定するのが Satisfy ディレクティブです。（Order ディレクティブでは環境変数などホスト以外での条件も書けますが、ここでは説明のため hostAccess を呼ぶことにします。）

上記の例では、Basic 認証と IP 制限のどちらか一方さえ満たせばアクセスできることになります。

より厳密には、アクセス制御に Require と Allow の両方が使われた場合に、全てを満たす必要があるか、一方さえ満たせばよいかを指定するのが Satisfy ディレクティブです。 http://httpd.apache.org/docs/current/en/mod/mod_access_compat.html#satisfy

Order ディレクティブと Satisfy ディレクティブは mod_access_compat モジュールが有効な場合に利用できます。

### Order Deny,Allow と Order Allow,Deny の違い

Order ディレクティブの書き方と、その評価結果について説明しておきます。

```
Order Deny,Allow
Deny from all
Allow from 127.0.0.1
```

と

```
Order Allow,Deny
Allow from 127.0.0.1
```

の評価結果は同じです。

- 前者は、「全てを許可する」が「all を拒否する」。ただし「127.0.0.1 は許可する」と読めます。
- 後者は、「全てを拒否する」が「127.0.0.1 は許可する」と読めます。

次の記述はどうでしょうか。

```
Order Allow,Deny
Deny from all
Allow from 127.0.0.1
Allow from 127.0.0.2
```

これは、Order をみれば後者のパターンです。故に、次のように評価されます。（なお、このように Deny や Allow は複数記述することができます。）

- 「全てを拒否する」が「127.0.0.1 と 127.0.0.2 は許可する」。ただし「all は拒否する」

アクセス元が 127.0.0.1 であっても最後の条件にマッチしてしまい、結果的に拒否されてしまいます。

Order の書き方について、これを文章として考えると最初に「全てを〜」と書かれている理由やその評価順序が分かりにくいので、文章的ではなく論理的に考えてみることにします。

Order の評価結果は http://httpd.apache.org/docs/current/ja/mod/mod_access_compat.html#order では次のような表で示されています。

| マッチ ＼ Order | Allow,Deny 時の結果 | Deny,Allow 時の結果 |
| :---: | :---: | :---: |
| Allow だけにマッチ | 許可 | 許可 |
| Deny だけにマッチ | 拒否 | 拒否 |
| どちらにもマッチしない | 拒否 | 許可 |
| Allow と Deny 両方にマッチ | 拒否 | 許可 |

この論理値表に一致するのは、次のような論理演算です。

| マッチ ＼ 論理演算 | ALLOW and not DENY | ALLOW or not DENY |
| :---: | :---: | :---: |
| Allow だけにマッチ | 許可 (T and T) | 許可 (T or T) |
| Deny だけにマッチ | 拒否 (F and F) | 拒否 (F or F) |
| どちらにもマッチしない | 拒否 (F and T) | 許可 (F or T) |
| Allow と Deny 両方にマッチ | 拒否 (T and F) | 許可 (T or F) |

ALLOW は Allow にマッチした場合に限り True となり、DENY は Deny にマッチした場合に限り True となります。その真偽値の論理演算結果が True であれば「許可」、False であれば「拒否」となります。

- Order Allow,Deny
    - Allow にマッチし、Deny にマッチしないときに限り「許可」
    - Allow にマッチしなければ「拒否」
    - Deny にマッチしたら「拒否」
- Order Deny,Allow
    - Allow にマッチしたら「許可」
    - Deny にマッチしなければ「許可」
    - Allow にマッチせず、Deny にマッチするときに限り「拒否」

### mod_authz_core モジュールの Require ディレクティブ

Apache 2.4 (2.3) から利用可能になった mod_authz_core モジュールがあります。

これは、Apache 2.2 以前の hostAccess で記述していたアクセス制御の代わりに使えるものです。つまり mod_authz_core モジュールを使う場合は mod_access_compat モジュールを使う必要がなくなります。

httpd.apache.org/docs/current/en/mod/mod_access_compat.html には、mod_access_compat は後方互換のために残されているだけであると、次のように記述されています。

```
Compatibility:
  Available in Apache HTTP Server 2.3 as a compatibility module
  with previous versions of Apache httpd 2.x.
  The directives provided by this module have been deprecated
  by the new authz refactoring. Please see mod_authz_host

...

Note
  The directives provided by mod_access_compat have been deprecated
  by the new authz refactoring. Please see mod_authz_host.
```

ここで、Apache 2.4 でも従来の Order ディレクティブなどが使えるようにと mod_access_compat と mod_authz_core を混在させた場合に、意図しない影響を与える場合があります。

/etc/apache2/apache2.conf には、外部からのリクエストに対して ".ht" から始まるファイルへのアクセスを拒否するための記述があります。以下は Apache 2.4 での記述です。

```
#
# The following lines prevent .htaccess and .htpasswd files from being
# viewed by Web clients.
#
<FilesMatch "^\.ht">
        Require all denied
</FilesMatch>
```

このとき、Basic 認証と IP 制限によるアクセス制御を .htaccess で行うために、Satisfy ディレクティブを用いてしまうと意図しない結果を招いてしまいます。

```
<FilesMatch "^\.ht">
        Require all denied
</FilesMatch>

# authAccess
AuthUserFile /var/www/hosts/web/sandbox/.htpasswd
AuthName "Authorization Required"
AuthType Basic
Require valid-user

# hostAccess
Order Deny,Allow
Deny from all
Allow from 127.0.0.1

Satisfy Any
```

Satisfy が対象とするのは Require と Allow (Order) でした。そして `Satisfy Any` が指定されている場合は、その制限のうちの1つでも条件を満たせばアクセスができる状態になります。

上記のような設定では、authAccess か hostAccess の条件を満たす場合に ".ht" から始まるファイルへのアクセスが可能になってしまいます。Basic 認証のために .htpasswd ファイルを公開ディレクトリ下に設置する場合がありますが、これは ".htpasswd" ファイルへのアクセスを拒否する設定が有効であることが前提になります。

これは apache2.conf 内の記述が mod_access_compat モジュールを利用することを想定していないために起こります。この問題を回避するには、mod_access_compat を無効にして mod_authz_core のみを使うようにするか、従来の Order ディレクティブなど mod_access_compat も使えるようにする場合は、別途 ".ht" から始まるファイルへのアクセスを制御する記述を追加する必要があります。

この文書では、この問題の対策も含めて、ドットから始まる全てのファイル、ディレクトリへはアクセスを禁止するように設定しています。

```
# /etc/apache2/site-available/001-original.conf

<Directory /var/www/hosts/*>
  # Access denied to dot-files such as .git/*
  RedirectMatch 403 /\.
</Directory>
```

例外が指定可能なように RedirectMatch を用いています。例えば .public ディレクトリはアクセスを許可したい場合は `RedirectMatch 403 /\.(?!public/)` のように記述できます。

なお、mod_authz_core で Basic 認証と IP 制限によるアクセス制御を .htaccess で行うためには、次のように記述します。これは認証と IP 制限のどちらか一方を満たせばアクセスができるような設定です。

```
# authAccess
AuthUserFile /var/www/hosts/web/sandbox/.htpasswd
AuthName "Authorization Required"
AuthType Basic
Require valid-user

# hostAccess
Require ip 127.0.0.1
```

### RequireAny と RequireAll と RequireNone

先ほどの例で、認証と IP 制限のどちらも満たさなければアクセスできないようにするには、次のように記述します。

```
<RequireAll>
  # authAccess
  AuthUserFile /var/www/hosts/web/sandbox/.htpasswd
  AuthName "Authorization Required"
  AuthType Basic
  Require valid-user

  # hostAccess
  Require ip 127.0.0.1
</RequireAll>
```

兄弟関係にある Require ディレクティブは、親の `<Require*>` によって Any か All が決まりますが、`<Require*>` を省略した場合は `<RequireAny>` があるものとして解釈されます。また、`<RequireAny>` は入れ子にすることができます。

- RequireAny ディレクティブ
    - 子の Require のいずれかの条件にマッチすれば True
- RequireAll ディレクティブ
    - 子の Require のすべての条件にマッチすれば True
- RequireNone ディレクティブ
    - 子の Require のすべての条件にマッチしなければ True

例えば、http://example.com/ のルートへのリクエストは http://example.net/ に無条件でリダイレクトし、ルート以外へのリクエストに対しては Basic 認証と IP 制限のどちらかを満たせばアクセスを許可するが、特定の IP からはアクセスを拒否するという場合は次のように記述できます。

```
SetEnvIf Host ^example.com$ APEX_DOMAIN_REQUEST
SetEnvIf Request_URI ^/$ ROOT_REQUEST

<RequireAny>
  # http://example.com/ のルートへのリクエストであれば拒否しない
  <RequireAll>
    Require env APEX_DOMAIN_REQUEST
    Require env ROOT_REQUEST
  </RequireAll>

  <RequireAll>
    # ブラックリストに含まれる IP であれば拒否する
    <RequireNone>
      # 172.16.*.* と 192.168.24.1 を拒否する
      Require ip 172.16
      Require ip 192.168.24.1
    </RequireNone>

    # Basic 認証を通るか 127.0.0.1 からのアクセスであれば許可する
    <RequireAny>
      AuthUserFile /var/www/hosts/web/sandbox/.htpasswd
      AuthName "Authorization Required"
      AuthType Basic
      Require valid-user

      Require ip 127.0.0.1
    </RequireAny>
  </RequireAll>
</RequireAny>

# http://example.com/ のルートへのリクエストは http://example.net/ にリダイレクトする
RewriteEngine On
RewriteCond %{ENV:APEX_DOMAIN_REQUEST} 1
RewriteCond %{ENV:ROOT_REQUEST} 1
RewriteRule ^ http://example.net/ [R=308,L]
```

これは多少複雑な例ですが、例えば SetEnvIf ディレクティブは `<Require*>` 内に書くことができないといったことがあるため、このように記述を組み合わせる場合は構文にも注意する必要があります。

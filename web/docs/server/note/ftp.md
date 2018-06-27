# FTP補足情報

## vsftpd.conf の設定

以下では、FTP over Explicit SSL/TLS (Explicit FTPS) で接続できるようにし、暗号化なしの FTP 接続は受け付けないようにします。

```
$ sudo aptitude install vsftpd
$ sudo service vsftpd stop
$ sudo emacs /etc/vsftpd.conf
```

- listen=YES を使って IPv4 アクセスを許可する（listen_ipv6 は使わないでおく）
- write_enable=YES をアンコメントして FTP での write 権限を与える
- local_umask=022 をアンコメントして新規ファイルの権限を 666/777 から 644/755 に変更する
- ascii_upload_enable=YES をアンコメントして ascii upload を認める
- ascii_download_enable=YES をアンコメントして ascii download を認める
- chroot_local_user=YES をアンコメントする
    - chroot_local_user=YES の場合は、全てのユーザがログイン時のディレクトリよりも上位のディレクトリへ移動が制限され、例外のユーザのみが制限を解除される
    - chroot_local_user=NO の場合は、全てのユーザはログイン時のディレクトリよりも上位のディレクトリまで移動できるようになり、例外のユーザのみが移動を制限される
- chroot_list_enable=YES をアンコメントして chroot_local_user の例外を設定できるようにする
- chroot_list_file=/etc/vsftpd.chroot_list をアンコメントしてファイルを明示的に参照する
- ls_recurse_enable=YES をアンコメントしてサブディレクトリを再帰的に操作対象にする

chroot_list_file で指定したファイルを作成しておきます。

```
$ sudo touch /etc/vsftpd.chroot_list
```

更に、もともと設定項目が記述されていない以下の内容を追記します。

```
ssl_enable=YES
force_local_data_ssl=YES
force_local_logins_ssl=YES
ssl_tlsv1=YES
ssl_sslv2=NO
ssl_sslv3=NO
pasv_max_port=60099
pasv_min_port=60000
ssl_ciphers=HIGH
require_ssl_reuse=NO
```

そして、パッシブモードで用いられる範囲のポートを開放しておき、vsftpd を再起動します。

```
sudo ufw allow 60000:60099/tcp
sudo ufw reload
sudo service vsftpd restart
```

## local_umask を 002 にするケース

上記では local_umask を 022 に指定していますが、異なるユーザで共通のディレクトリを FTP 上から操作するために FTP 上で操作できるディレクトリに setgid 属性を与えるような運用をする場合には、local_umask を 022 ではなく 002 にします。これにより FTP クライアントでファイルやディレクトリを新規作成した場合に setgid による g+w 権限を引き継ぐことができるようになります。

## Explicit モードと Implicit モードについて

FTP over SSL/TLS (FTPS) には、暗号化通信の方法に Explicit モードと Implicit モードの2種類があります。これらはクライアントがサーバと通信をする際に、暗号化通信を開始するタイミングが異なります。

- Explicit モード
    - まず、クライアントがサーバに接続し、クライアントが AUTH コマンドを実行したらサーバは SSL/TLS プロトコルでのネゴシエーションを行い、暗号化通信が開始される。
    - クライアントが AUTH コマンドを実行しなければ通常の FTP として機能する。こうした仕組みであるため、ポートは FTP と同じ 21 番が使われる（デフォルトの場合）。
    - サーバ側では通常の FTP （非暗号化通信）を受け付けずに、暗号化通信のみを受け付けるように設定することもできる。
- Implicit モード
    - クライアントがサーバに接続すると、サーバは SSL/TLS プロトコルでの通信が開始される。
    - 990 番ポート（デフォルトの場合）を使って SSL/TLS する前提となっている。

Explicit モードでは、クライアントが FTPS 接続を要求すれば暗号化通信を、FTP 接続を要求すれば非暗号化通信をするというような対応が可能です。

Implicit モードは 990 番ポートを用いて強制的に暗号化通信をするというものですが、Explicit モードでも設定次第で通常の FTP 接続（非暗号化通信）を拒否することができます。

このため、Implicit モードを積極的に用いる理由はあまりなく、サーバ側で SSL/TLS 通信のみを受け付けるようにしておき、クライアントは Explicit FTPS 接続（AUTH コマンドを実行する接続）を行うようにすることでセキュアな FTP 接続が確立できるようになります。

## アクティブモードとパッシブモードについて

FTP には、接続方法にアクティブモードとパッシブモードというものがあります。FTP での通信にはクライアントとサーバ間の制御コネクションとデータコネクションがあります。

```
Client <---------> Server [Control Connection]
Client <=========> Server [Data Connection]
Client <---------> Server [Control Connection]
Client <=========> Server [Data Connection]
Client <=========> Server [Data Connection]
...
```

アクティブモードは、コネクションを次のように行います。サーバ IP が 192.168.0.1 で、クライアント待ち受けポートが 3001 の例です。

```
# クライアント側からサーバに 11*256+185 (=3001) 番ポートを開けて Client が待つことを伝える
Client:*    ----------> Server:21 [ `PORT 192,168,0,1,11,185` ]
Client:*    <---------- Server:21 [ FTP Response: 200 Command okay ]
Client:3001 [ Waiting ]

# クライアント側から FTP Request を送る
Client:*    ----------> Server:21 [ FTP Request ]

# サーバ側からクライアントにコネクションの確立 (3-way-handshake) を行う
Client:3001 <========== Server:20 [ SYN ]
Client:3001 ==========> Server:20 [ SYN-ACK ]
Client:3001 <========== Server:20 [ ACK ]

# 3-way-handshake が完了したらデータ転送に移る
Client:*    <---------> Server:21 [ Some Information ]
Client:3001 <=========> Server:20 [ Data Transfer ]

# データ転送が完了したらコネクションの切断を行う
Client:3001 ==========> Server:20 [ FIN-ACK ]
Client:3001 <========== Server:20 [ ACK ]
Client:3001 <========== Server:20 [ FIN-ACK ]
Client:3001 ==========> Server:20 [ ACK ]
Client:*    <---------- Server:21 [ FTP Response: 226 Closing data connection ]
```

FTP Request の内容によって多少異なりますが、上記のような流れになります。制御コネクションは Client:xx （任意のポート）と Server:21 間で通信され、データコネクションは Client:yy （待ち受けポート）と Server:20 間で通信されます。

アクティブモードでは、データコネクションの開始をサーバ側から行います。サーバ側からの通信は次の箇所です。

```
# サーバ側からクライアントにコネクションの確立 (3-way-handshake) を行う
Client:3001 <========== Server:20 [ SYN ]
```

しかし、クライアント側のファイアウォールが有効な場合、外部からクライアント内部への TCP 接続（SYN パケット）は拒否されてしまうことがあります。このため、TCP 接続をクライアント側からのみ行って、クライアント側のファイアウォールを活かしたまま FTP 通信を行うパッシブモードが用意されています。

クライアント側のファイアウォールの中には、PORT コマンドで待ち受けるポートを開放してアクティブモードでも接続できるようにするものもあるようですが、サーバ側でパッシブモードをサポートしている場合は、一般的にはパッシブモードでの接続が用いられます。

パッシブモードでは、コネクションを次のように行います。クライアント IP が 192.168.24.1 で、サーバ待ち受けポートが 60000 の例です。

```
# クライアント側からサーバにパッシブモード接続の要求をする
Client:* ----------> Server:21 [ `PASV` ]
Client:* <---------- Server:21 [ FTP Response: 227 Entering passive mode (192,168,24,1,234,96) ]
                     Server:60000 [ Waiting ]

# クライアント側からサーバにコネクションの確立 (3-way-handshake) を行う
Client:* ==========> Server:60000 [ SYN ]
Client:* <========== Server:60000 [ SYN-ACK ]
Client:* ==========> Server:60000 [ ACK ]

# クライアント側から FTP Request を送る
Client:* ----------> Server:21 [ FTP Request ]

# 3-way-handshake が完了したらデータ転送に移る
Client:* <---------> Server:21 [ Some Information ]
Client:* <=========> Server:60000 [ Data Transfer ]

# データ転送が完了したらコネクションの切断を行う
Client:* ==========> Server:60000 [ FIN-ACK ]
Client:* <========== Server:60000 [ ACK ]
Client:* <========== Server:60000 [ FIN-ACK ]
Client:* ==========> Server:60000 [ ACK ]
Client:* <---------- Server:21 [ FTP Response: 226 Closing data connection ]
```

パッシブモードの場合は、制御コネクションはアクティブモードと同様に Client:xx （任意のポート）と Server:21 間で通信され、データコネクションは Client:xx （任意のポート）と Server:yy （待ち受けポート）間で通信されます。

待ち受けポートについて、データ転送の完了後にすぐに同じポートへの接続要求を受け付けることができないという制約があります。パッシブモードでは特に、サーバ側の設定で待ち受けポートを複数、幅を持たせて用意するようにします。

## allow_writeable_chroot=YES について

vsftpd は現在 v3.0.2 が最新版です。リリース日については https://security.appspot.com/vsftpd.html に次のように書かれています。

```
Sep 2012 - vsftpd-3.0.2 released with seccomp sandbox fixes
Apr 2012 - vsftpd-3.0.0 released with a seccomp filter sandbox
Dec 2011 - vsftpd-2.3.5 released
```

ユーザが FTP 上で移動できるディレクトリのルートを指定することができますが、それを行った場合にはルートディレクトリ自体に write 権限が付いているとセキュリティ上の理由でエラーとなるように vsftpd v2.3.5 で仕様が変更されました。

これは、書き込み権限のあるディレクトリを不注意で FTP 上から変更できてしまうことを防ぐもののようですが、これではユーザの FTP 上のルートディレクトリを変更する際に不都合が生じてしまいます。

そのため vsftpd v3.0.0 では、v2.3.5 で施された仕様変更に対して、ルートディレクトリに write 権限が付いていても明示的に allow_writeable_chroot=YES が設定されていればエラーとならないようなオプションが追加されました。

これについては vsftpd 公式サイトの Changelog ( https://security.appspot.com/vsftpd/Changelog.txt ) のコメントから状況を追うことができます。

```
- Add stronger checks for the configuration error of running with a writeable
root directory inside a chroot(). This may bite people who carelessly turned
on chroot_local_user but such is life.

vsftpd v2.3.5
```

- （拙訳）chroot() 内で書き込み権限を持ったルートディレクトリが設定されている場合に、実行時エラーとなるようなチェックを追加しました。これは、不注意で chroot_local_user を有効にした人を困らせてしまうかもしれませんが仕方なくそうしています。(vsftpd v2.3.5)

```
- Add new config setting "allow_writeable_chroot" to help people in a bit of
a spot with the v2.3.5 defensive change. Only applies to non-anonymous.

vsftpd v3.0.0
```

- （拙訳）v2.3.5 で追加された安全のための変更で困っている人のために、新しい設定項目 "allow_writeable_chroot" を追加しました。これは匿名でないユーザのみに適用されます。(vsftpd v3.0.0)

余談ですが、v3.0.0 がリリースされる前には v2.3.5 でのこの問題を回避するために、非公式に vsftpd を拡張している vsftpd-ext ( http://vsftpd.devnet.ru/eng/ ) で同様のオプションがサポートされていました。vsftpd-ext v2.3.5 では "allow_writable_chroot" という設定項目（writeable ではなく writable）だったようです。

# SSL接続

## # SSH 接続時に警告が表示される場合

過去に当該ホストに接続したことがある場合、ssh コマンド実行時に以下のような警告が出ることがあります。

```
@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
@    WARNING: REMOTE HOST IDENTIFICATION HAS CHANGED!     @
@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
IT IS POSSIBLE THAT SOMEONE IS DOING SOMETHING NASTY!
Someone could be eavesdropping on you right now (man-in-the-middle attack)!
```

自身で OS の再インストールをした場合など、警告が出る理由が自明であれば ~/.ssh/known_hosts から当該のホストの情報を削除することで警告を抑制できます。以下のコマンドを実行すれば情報を削除できます。

```
$ ssh-keygen -R yourhost
```

ただし、警告の抑制は、警告が表示される理由を理解した上で行ってください。

## # 警告が表示される理由

この警告は、SSH 接続先サーバの公開鍵が前回の接続時から変更されたことを検知しています。

接続先サーバの環境を変更した覚えもないのにこの警告が表示される場合は、悪意のある第三者から通信経路上で何らかの攻撃（中間者攻撃：man-in-the-middle attack, MITM攻撃）を受けている可能性があります。

中間者攻撃の可能性が考えられる場合は、Fingerprint を確認するなどして接続先サーバが正当なものかを確認してください。この警告を無視して安易に ssh-keygen -R コマンドを実行すべきではありません。

サーバ側では SSH 接続時に使われる公開鍵の Fingerprint は `$ ssh-keygen -lf /path/to/key.pub` のようにコマンドを実行すると確認することができます。

初めてサーバにアクセスするような場合にも、この Fingerprint の確認が行われます。

```
The authenticity of host 'example.com (192.168.0.1)' can't be established.
ECDSA key fingerprint is ca:42:8a:b3:e0:a7:49:7f:b1:0b:bf:44:e4:67:21:4a.
Are you sure you want to continue connecting (yes/no)?
```

この例では ECDSA 鍵が使われているため、サーバ側で Fingerprint を確認するには、次のようにコマンドを実行します。

```
$ ssh-keygen -lf /etc/ssh/ssh_host_ecdsa_key.pub
```

### # SSH プロトコルについて

二者間で通信をする際にはその通信内容を第三者から保護するために、機密性と完全性を確保する仕組みが必要になります。機密性は、第三者に内容（平文）を盗聴されないようにすること、完全性は、第三者に内容を改竄されないようにすることを意味します。

SSH には、SSH1 プロトコルと SSH2 プロトコルがあります。

SSH1 はもともと使われていたもので、機密性のために RSA を用い、完全性のために CRC を用いていましたが、RSA 暗号方式に特許があったため、RSA を用いない SSH2 が当時実装されました。

SSH2 では機密性のために DSA を用い、完全性のために CRC よりも強力な HMAC が用いられていました。しかし、2000年9月に RSA の特許が無効となったため、SSH2 では DSA と RSA のどちらも使うことが可能になっています。

現在では、完全性を確認するアルゴリズムが強力な SSH2 プロトコルを用いることが好ましいとされています。ただし、SSH2 は SSH1 の上位互換ではないため、サーバが SSH1 プロトコルしか対応していない場合は、クライアント側も SSH1 プロトコルに対応していないと SSH 接続を行うことができません。

### # SSH ホスト認証時に使われる暗号方式

SSH 接続を行うクライアント側では、SSH クライアントソフトが対応している暗号方式を用いてホスト認証を行います。

サーバ側が SSH2 プロトコルをサポートする場合、DSA 暗号方式と RSA 暗号方式のどちらも受け付けられるように、それぞれの公開鍵と秘密鍵が用意されています。

この文書で構築したサーバには、以下の様な鍵が用意されています。

```
$ ls -1 /etc/ssh/ssh_host_*
ssh_host_dsa_key
ssh_host_dsa_key.pub
ssh_host_ecdsa_key
ssh_host_ecdsa_key.pub
ssh_host_ed25519_key
ssh_host_ed25519_key.pub
ssh_host_rsa_key
ssh_host_rsa_key.pub
```

ここでは RSA 鍵、DSA 鍵に加えて、楕円曲線 DSA (ECDSA) 鍵、エドワーズ曲線 DSA (EdDSA) 鍵が用意されています。

なお、「RSA」は発明者である3人 Ron Rivest, Adi Shamir, Len Adleman の頭文字を取ったもので、「DSA」はデジタル署名アルゴリズム (Digital Signature Algorithm) の略、「EC」は楕円曲線 (Elliptic Curve) の略、「Ed」は Edwards-curve から取ったものです。「Ed25519」は、Daniel Bernstein 氏によって開発された Curve25519 と呼ばれる楕円曲線を用いた、エドワーズ曲線 DSA の実装です。

Mac OS X のデフォルトの SSH クライアントでは、おそらくデフォルトでは RSA が使われるようになっていますが、~/.ssh/config に次のように HostKeyAlgorithms の設定項目を記述することで、ホスト認証時に用いる暗号方式の優先度を設定できます。

```
HostKeyAlgorithms ssh-dss,ssh-rsa
```

このような記述を設定した上で ssh コマンドを実行すれば Fingerprint の確認の際に RSA 鍵ではなく DSA 鍵が表示されるはずです。なお、Mac OS X のデフォルトの SSH クライアントは ECDSA 鍵や EdDSA 鍵には対応していないようなので、Mac でこれらを使いたい場合は Homebrew を用いて `$ brew install openssh` を実行するなどして（予め `$ brew tap homebrew/dupes` を実行している必要があります）、対応している SSH クライアントを用いるようにするとよいかもしれません。

ここで、ssh-dss と指定していますが、「DSS」は Digital Signature Standard の略です。連邦情報処理標準 (Federal Information Processing Standard, FIPS) が定義する標準規格 FIPS 186 としてデジタル署名の標準規格が定められました。制定当初は DSS には DSA しか含まれていませんでしたが、その後の改定で DSA の他に RSA, ECDSA も含まれました。ただし、上記のように ssh-dss と書いた場合は DSA が用いられます。

#### # HostKeyAlgorithms の設定値について

HostKeyAlgorithms の設定値と対応する暗号方式は次のようになっています。

| 設定値 | 暗号方式 |
| :---: | :---: |
| ssh-rsa | RSA |
| ssh-dss | DSA |
| ecdsa-sha2-nistp256 | ECDSA |
| ecdsa-sha2-nistp384 | ECDSA |
| ecdsa-sha2-nistp521 | ECDSA |
| ssh-ed25519 | Ed25519 |

設定可能な値は ssh_config(5) の man page の HostKeyAlgorithms の項目に書かれています。

```
HostKeyAlgorithms
  Specifies the protocol version 2 host key algorithms that the client wants to use in order of preference.
  The default for this option is:

     ecdsa-sha2-nistp256-cert-v01@openssh.com,
     ecdsa-sha2-nistp384-cert-v01@openssh.com,
     ecdsa-sha2-nistp521-cert-v01@openssh.com,
     ssh-ed25519-cert-v01@openssh.com,
     ssh-rsa-cert-v01@openssh.com,ssh-dss-cert-v01@openssh.com,
     ssh-rsa-cert-v00@openssh.com,ssh-dss-cert-v00@openssh.com,
     ecdsa-sha2-nistp256,ecdsa-sha2-nistp384,ecdsa-sha2-nistp521,
     ssh-ed25519,ssh-rsa,ssh-dss

  If hostkeys are known for the destination host then this default is modified to prefer their algorithms.

  The list of available key types may also be obtained using the -Q option of ssh(1) with an argument of ``key''.
```

なお、ECDSA は、2011年1月24日にリリースされた OpenSSH 5.7 で、Ed25519 は、2014年1月30日にリリースされた OpenSSH 6.5 でサポートされています。

- http://www.openssh.com/txt/release-5.7
- http://www.openssh.com/txt/release-6.5

#### # ECDSA のビット長について

ECDSA のビット長については、楕円曲線暗号および楕円曲線アルゴリズムに関する RFC 5639, 5656 で定められています。

```
# https://tools.ietf.org/html/rfc5639#section-2.2

2.2.  Technical Requirements

   Commercial demands and experience with existing implementations lead
   to the following technical requirements for the elliptic curve domain
   parameters.

   1.  For each of the bit lengths 160, 192, 224, 256, 320, 384, and
       512, one curve shall be proposed.  This requirement follows from
       the need for curves providing different levels of security that
       are appropriate for the underlying symmetric algorithms.  The
       existing standards specify a 521-bit curve instead of a 512-bit
       curve.
```

- （拙訳）この曲線は、次のそれぞれのビット長 160, 192, 224, 256, 320, 384, 512 に対して提唱されます。この必要条件は、根底にある対称アルゴリズムを適切にするための異なるセキュリティレベルの曲線を与えるという要求に従っています。このため、既存の規格では 512 ビット長の曲線の代わりに 521 ビット長の曲線とする仕様にしています。
    - （補足）160 (= 32\*5), 192 (= 32\*6), 224 (= 32\*7), 256 (= 32\*8, 128\*2), 320 (= 32\*10), 384 (= 32\*12, 128\*3), 512 (= 32\*16, 128\*4)

```
# http://tools.ietf.org/html/rfc5656#section-10.1

10.1.  Required Curves

   Every SSH ECC implementation MUST support the named curves below.
   These curves are defined in [SEC2]; the NIST curves were originally
   defined in [NIST-CURVES].  These curves SHOULD always be enabled
   unless specifically disabled by local security policy.

              +----------+-----------+---------------------+
              |   NIST*  |    SEC    |         OID         |
              +----------+-----------+---------------------+
              | nistp256 | secp256r1 | 1.2.840.10045.3.1.7 |
              |          |           |                     |
              | nistp384 | secp384r1 |     1.3.132.0.34    |
              |          |           |                     |
              | nistp521 | secp521r1 |     1.3.132.0.35    |
              +----------+-----------+---------------------+

      *  For these three REQUIRED curves, the elliptic curve domain
         parameter identifier is the string in the first column of the
         table, the NIST name of the curve.  (See Section 6.1.)
```

- （拙訳）全ての SSH 楕円曲線暗号の実装は、以下に挙げられている曲線をサポートしなければなりません。これらの曲線は [SEC2] で定義されています。NIST の曲線については元々の [NIST-CURVES] に定義されています。これらの曲線は、ローカルのセキュリティポリシーで明示的に無効になっていない限りは常に有効になっているべきです。
    - nistp256
    - nistp384
    - nistp521
- （拙訳）これらの3つの必須な曲線について、楕円曲線のドメインパラメータ識別子はこの表の最初の列の文字列であり、曲線の NIST 名です。

NIST は米国国立標準技術研究所 (National Institute of Standards and Technology) の略称です。

RSA と比べると楕円曲線暗号 (ECC) のビット長は少ないですが、その暗号強度の比較は次のように書かれています。ECC で 256 ビットあれば、その強度は少なくとも RSA の 2048 ビット以上に相当するということです。

```
# http://tools.ietf.org/html/rfc5656#section-1

      +-----------+------------------------------+-------+---------+
      | Symmetric | Discrete Log (e.g., DSA, DH) |  RSA  |   ECC   |
      +-----------+------------------------------+-------+---------+
      |     80    |       L = 1024, N = 160      |  1024 | 160-223 |
      |           |                              |       |         |
      |    112    |       L = 2048, N = 256      |  2048 | 224-255 |
      |           |                              |       |         |
      |    128    |       L = 3072, N = 256      |  3072 | 256-383 |
      |           |                              |       |         |
      |    192    |       L = 7680, N = 384      |  7680 | 384-511 |
      |           |                              |       |         |
      |    256    |      L = 15360, N = 512      | 15360 |   512+  |
      +-----------+------------------------------+-------+---------+
```

### # Fingerprint の表示形式について

SSH ホスト認証時に使われる暗号方式に種類があることについては前述の通りですが、OpenSSH 6.8 では、それ以前のバージョンとは異なり Fingerprint に用いるアルゴリズムと表示形式が変更されました。http://www.openssh.com/txt/release-6.8 には次のように書かれています。

```
 * Add FingerprintHash option to ssh(1) and sshd(8), and equivalent
   command-line flags to the other tools to control algorithm used
   for key fingerprints. The default changes from MD5 to SHA256 and
   format from hex to base64.

   Fingerprints now have the hash algorithm prepended. An example of
   the new format: SHA256:mVPwvezndPv/ARoIadVY98vAC0g+P/5633yTC4d/wXE
   Please note that visual host keys will also be different.
```

- （拙訳）FingerprintHash オプションを ssh と sshd に追加し、また鍵の指紋 (Fingerprint) のために使われるアルゴリズムを制御する他のツールにも同様のコマンドラインフラグを追加します。そのデフォルト値は、MD5 の hex 表記だったものから、SHA256 の base64 表記に変更されます。
- （拙訳）鍵の指紋 (Fingerprint) には、そのハッシュ値の手前にハッシュアルゴリズムが付与されるようになります。例として、新しい形式では `SHA256:mVPwvezndPv/ARoIadVY98vAC0g+P/5633yTC4d/wXE` のようになります。ホスト鍵を可視化する際にもこの違いがあることに注意してください。

これまでは、以下の様に MD5 値の 16 進 (hex) 表記でした。

```
The authenticity of host 'example.com (192.168.0.1)' can't be established.
ECDSA key fingerprint is ca:42:8a:b3:e0:a7:49:7f:b1:0b:bf:44:e4:67:21:4a.
Are you sure you want to continue connecting (yes/no)?
```

この表記が、次のように SHA256 値の base64 表記になります。

```
The authenticity of host 'example.com (192.168.0.1)' can't be established.
ECDSA key fingerprint is SHA256:TietI8DrsRIxNbQwI55s9dyejGkQE6Xt479z77BpkPk.
Are you sure you want to continue connecting (yes/no)?
```

SSH クライアント側で、この表記を MD5 のものにするためには ~/.ssh/config に次のように FingerprintHash の設定項目を記述します。

```
FingerprintHash md5
```

このように設定を記述していると、ssh コマンド実行時の Fingerprint が次のように表示されます。

```
The authenticity of host 'example.com (192.168.0.1)' can't be established.
ECDSA key fingerprint is MD5:ca:42:8a:b3:e0:a7:49:7f:b1:0b:bf:44:e4:67:21:4a.
Are you sure you want to continue connecting (yes/no)?
```

サーバ側で Fingerprint を確認する際にも、OpenSSH のバージョンが 6.8 以上の場合には、FingerprintHash オプションを指定しない場合には SHA256 の base64 表記が出力されてしまいます。

ssh-keygen コマンドでは、E オプションに続けて fingerprint_hash を指定することができます。

```
$ ssh-keygen -lf /etc/ssh/ssh_host_ecdsa_key.pub
$ ssh-keygen -E md5 -lf /etc/ssh/ssh_host_ecdsa_key.pub
```

E オプションで md5 を指定すれば MD5 ハッシュ値を確認することができます。

#### # ssh(1) や sshd(8) の数字の意味

man コマンドでは、各コマンドのマニュアル (manual page, man page) を見ることができますが、この man page はコマンドがセクション別に書かれています。

`$ man man` を実行して確認できるマニュアルには次のように書かれています。

```
The table below shows the section numbers of the manual followed by the types of pages they contain.

1   Executable programs or shell commands
2   System calls (functions provided by the kernel)
3   Library calls (functions within program libraries)
4   Special files (usually found in /dev)
5   File formats and conventions eg /etc/passwd
6   Games
7   Miscellaneous (including macro packages and conventions), e.g. man(7), groff(7)
8   System administration commands (usually only for root)
9   Kernel routines [Non standard]
```

ここでいう `ssh(1)` は、セクション 1 に属する ssh であり、`sshd(8)` は、セクション 8 に属する sshd であるということを表しています。

異なるセクションに同名のものがあるものがあります。例えば passwd は、passwd(1) の他に、上記のセクション 5 の概要に書かれているように passwd(5) もあります。

`$ man -f passwd` と passwd という名のページを検索してみると、該当するものがリストアップされます。なお、以下の (1ssl) のように、あるコマンド特有のマニュアルについては、セクション名にそのコマンド名が付与されています。

```
passwd (1)           - change user password
passwd (1ssl)        - compute password hashes
passwd (5)           - the password file
```

`$ man passwd` と打つと passwd(1) のマニュアルが表示されます。同名のページがある場合に特定のセクションのマニュアルを開くには、`$ man 5 passwd` のように引数を2つ渡して man コマンドを実行します。

man に存在するページの一覧は `$ man -k ''` とでも実行すれば確認できます。`$ man -k <pattern>` でその正規表現のパターンにマッチするページの一覧が得られます。

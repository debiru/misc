# MySQL補足情報

## ユーザの作成について

```
mysql> CREATE USER mysqluser@localhost IDENTIFIED by 'thepassword';
```

これで `-h` オプションを伴わない `$ mysql -u mysqluser -p` のようなコマンドを実行し、パスワード `thepassword` を入力すればログインできます。`-h` オプションを使わずにローカルホストの MySQL に接続を試みた場合には、（そのコマンドを打った）クライアントと MySQL サーバ間で TCP/IP 接続ではなくソケット (UNIX Socket) による接続が行われます。

phpMyAdmin を利用するような場合でも、その Web サーバと MySQL サーバが同一であれば、利用する MySQL ユーザのホストを localhost としておけば接続できます。

ユーザ作成時のホスト部指定について、MySQL に接続を行うクライアントとサーバが同一サーバであっても、localhost に対してソケット接続を行うのか、127.0.0.1 に対して TCP/IP 接続を行うのかによってログインの可否が異なります。具体的には、`user@127.0.0.1` アカウントがある場合には `$ mysql -u user -p` ではログインできず、`$ mysql -h 127.0.0.1 -u user -p` とする必要があります。

一方、ホスト名の名前解決が行われる場合（`--skip-name-resolve` オプションが無効な場合）、ホスト 127.0.0.1 に対するログイン時には、localhost アカウントでもログインが可能になります。これは 127.0.0.1 に対する名前解決によって逆引きで localhost がマッピングされるためで、このことは MySQL のマニュアルに記載されています。

- MySQL :: MySQL 5.6 Reference Manual :: 5.1.3 Server Command Options
    - https://dev.mysql.com/doc/refman/5.6/en/server-options.html#option_mysqld_skip-name-resolve

余談ですが、127.0.0.1 が localhost にマッピングされる仕様について、バグではないかという指摘がされていますが、マニュアルへの説明も追加されないままこの仕様は放置されているようです。

- MySQL Bugs: #68436: user@127.0.0.1 is authorized partly as user@localhost.
    - http://bugs.mysql.com/bug.php?id=68436

ユーザを作成したら、続けて root ユーザ権限で作成したユーザに権限を付与します。以下のコマンドは全データベース、全テーブルへの操作権限（グローバルレベルの権限）を与えます。

```
# 権限を付ける
mysql> GRANT ALL PRIVILEGES ON *.* TO mysqluser@localhost;

# 権限を外す
mysql> REVOKE ALL PRIVILEGES ON *.* FROM mysqluser@localhost;
```

グローバルレベルに権限を与えてしまうと、`mysql` データベース等の操作までできてしまいます。特定のデータベースのみに権限を与えるためには、アスタリスクではなく具体的なデータベース名を記述します。

```
mysql> GRANT ALL PRIVILEGES ON test_db.* TO mysqluser@localhost;
```

これで `test_db` データベースを操作する権限を与えたように見えますが、ユーザに対する権限におけるデータベース名およびテーブル名では `_` および `%` のワイルドカードが使用できます。

`_` は任意の1文字を表すため、上記の記述では `testxdb` データベースを作成したりすることもできてしまいます。アンダースコアを文字として扱いたければ、バックスラッシュによるエスケープシーケンスを用いる必要があります。

```
mysql> GRANT ALL PRIVILEGES ON `test\_db`.* TO mysqluser@localhost;
```

`%` は任意の0文字以上を表すため、`test_` から始まる名前のデータベースのみ作成、削除、操作を自由に行える権限を与えるためには、次のようにコマンドを実行します。

```
mysql> GRANT ALL PRIVILEGES ON `test\_%`.* TO mysqluser@localhost;
```

- MySQL :: MySQL 5.6 Reference Manual :: 13.7.1.4 GRANT Syntax
    - https://dev.mysql.com/doc/refman/5.6/en/grant.html#grant-accounts-passwords

グローバルレベルの権限は mysql.user テーブルで確認できます。特定のデータベース、テーブルへの権限は mysql.db テーブルで確認できます。

ユーザおよび、ユーザへの権限を確認します。

```
mysql> SELECT Host, User, Password FROM mysql.user;
mysql> SELECT * FROM mysql.user\G
mysql> SELECT * FROM mysql.db\G
```

もしパスワードが空のアカウントがあれば、意図的でない限り必ず全てのアカウントにパスワードを設定してください。root ユーザのパスワードを変更したりした場合には、その後で MySQL を再起動します。Ubuntu の場合は MySQL のサービス名は mysqld ではなく mysql となっています。

```
mysql> UPDATE mysql.user SET password=PASSWORD('thepassword') where User='root';
```

```
$ sudo service mysql restart
```

## 照合順序について

- MySQL 5.7 時点の utf8mb4 に関する Collation
    - utf8mb4_unicode_ci
    - utf8mb4_unicode_520_ci
    - utf8mb4_general_ci
    - utf8mb4_bin
- Case-Sensitive と Case-Insensitive を評価する文字
    1. 【A：a】ASCII文字の大文字・小文字
    2. 【は：ハ】ひらがな・カタカナ
    3. 【よ：ょ】日本語の大文字と小文字
    4. 【は：ぱ：ば】清音・濁音・半濁音
    5. 【ハ：ﾊ】全角・半角
    6. 【🍣：🍺】任意の絵文字

```
+---+----------------------+-------------------+--------------------+------------------------+--------------------+-------------+--------|
| # | Case                 | Sample Characters | utf8mb4_unicode_ci | utf8mb4_unicode_520_ci | utf8mb4_general_ci | utf8mb4_bin | EXPECT |
+---+----------------------+-------------------+--------------------+------------------------+--------------------+-------------+--------|
| 1 | ASCII Upper/Lower    | A, a              | CI (good)          | CI (good)              | CI (good)          | CS (unkind) | CI     |
| 2 | Hiragana-Katakana    | は, ハ            | CI (good)          | CI (good)              | CS (unkind)        | CS (unkind) | CI     |
| 3 | Japanese Upper/Lower | よ, ょ            | CI (buggy)         | CI (buggy)             | CS (good)          | CS (good)   | CS     |
| 4 | Seion-(Han-)Dakuon   | は, ば, ぱ        | CI (buggy)         | CI (buggy)             | CS (good)          | CS (good)   | CS     |
| 5 | Wide-Narrow          | ハ, ﾊ, Ｍｙ, My   | CI (good)          | CI (good)              | CS (unkind)        | CS (unkind) | CI     |
| 6 | Emoji                | 🍣, 🍺            | CI (buggy)         | CS (good)              | CI (buggy)         | CS (good)   | CS     |
+---+----------------------+-------------------+--------------------+------------------------+--------------------+-------------+--------|
```

- 「ハハ」と「パパ」を同一視してしまうといった Buggy な挙動を含む照合順序を除くと、採用すべき照合順序としては選択肢に utf8mb4_bin しか残らない。
- 絵文字を無視すれば utf8mb4_general_ci も選択肢に残る。

この件については [MySQL Bugs: #79977: utf8mb4_unicode_520_ci don't make sense for Japanese FTS](https://bugs.mysql.com/bug.php?id=79977) で期待する照合順序の実装の要望が扱われており、utf8mb4_ja_0900_as_cs が MySQL 8.0.1 で追加される予定であるが、使用する場合は `ja_0900_as_cs` という名称に関して何が CS として扱われるか（ASCII Upper/Lower は区別されるのか）について、期待する結果が得られるかをきちんと確認した方がよさそうである。（参照：[日々の覚書: MySQL 8.0.1でutf8mb4_ja_0900_as_csが導入された](https://yoku0825.blogspot.jp/2017/04/mysql-801utf8mb4ja0900ascs.html)）

## 文字コードについて

MySQL の Charset には次の種類がある。

- サーバ
- クライアント
- サーバとクライアント間のコネクション
- データベース
- テーブル
- カラム

なお、my.cnf における各セクションの意味は次の通り。

- `[mysqld]`：MySQL Daemon（サーバ側）が参照するオプション
- `[client]`：MySQL 接続クライアントが参照するオプション
- `[mysql]`：MySQL コマンドラインツールの mysql が参照するオプション

MySQL 自体の文字コード設定を確認する場合は、SHOW VARIABLES を実行すればよい。

```
mysql> SHOW VARIABLES LIKE 'char%';
```

各データベース内の文字コード設定を確認する場合は、`information_schema` データベース内の `schemata`, `tables`, `columns` テーブルを `SELECT` で参照すればよい。

```
mysql> SELECT * FROM information_schema.schemata;
```

### (MySQL Charset) サーバ

新規データベースの Charset のデフォルト値として使用される。

- mysqld の起動時オプション `--character-set-server=utf8mb4`
- my.cnf の [mysqld] セクション `character-set-server = utf8mb4`
- サーバ変数 `character_set_server`

my.cnf で設定する場合は次のように指定すればよい。

- [MySQL :: MySQL 5.6 リファレンスマニュアル :: 10.1.5 アプリケーションの文字セットおよび照合順序の構成](https://dev.mysql.com/doc/refman/5.6/ja/charset-applications.html)

```
[mysqld]
character-set-server = utf8mb4
collation-server = utf8mb4_bin
```

文字コード設定におけるどの項目においても、Charset が指定されているが Collation が指定されていない場合には、Collation には指定した Charset のデフォルト値が使われる点に注意しなければならない。utf8mb4 の場合、デフォルトの Collation は MySQL 5.7 時点で utf8mb4_general_ci である（`mysql> SHOW CHARACTER SET;` で確認できる）。

例えば、`character-set-server=utf8mb4` および `collation-server=utf8mb4_bin` を指定している場合でも、`CREATE DATABASE db_name CHARSET utf8mb4;` のように Collation を省略した場合、`collation-server=utf8mb4_bin` の設定は無視される。

- [MySQL :: MySQL 5.6 リファレンスマニュアル :: 10.1.3.2 データベース文字セットおよび照合順序](https://dev.mysql.com/doc/refman/5.6/ja/charset-database.html)

> MySQL では、データベース文字セットとデータベース照合順序が次のように選択されます。
> - CHARACTER SET X と COLLATE Y の両方が指定されている場合、文字セット X と照合順序 Y が使用されます。
> - CHARACTER SET X は指定されているが COLLATE は指定されていない場合、文字セット X とそのデフォルト照合順序が使用されます。各文字セットのデフォルトの照合順序を確認するには、SHOW COLLATION ステートメントを使用します。
> - COLLATE Y は指定されているが CHARACTER SET は指定されていない場合、Y に関連付けられた文字セットと照合順序 Y が使用されます。
> - これ以外の場合は、サーバー文字セットとサーバー照合順序が使用されます。

### (MySQL Charset) クライアント

データベースに接続するクライアント側の設定値であり、クライアント側の文字コードとして使用される。また、クライアントはサーバにこの文字コードを送信し、サーバはそれらを character_set_client、character_set_results、および character_set_connection に設定しようとする。

- mysql の接続時オプション `--defaultcharacter-set=utf8mb4`
- my.cnf の [client] セクション `default-character-set = utf8mb4`
- 未指定の場合は latin1

my.cnf で設定する場合は次のように指定すればよい。

- [MySQL :: MySQL 5.6 リファレンスマニュアル :: 10.1.4 接続文字セットおよび照合順序](https://dev.mysql.com/doc/refman/5.6/ja/charset-connection.html)
    - ドキュメントでは `[mysql]` セクションに書いているが `[client]` セクションに書いても mysql コマンドは参照できる。
    - クライアントが `[client]` に設定されている全てのオプションを使用できるとは限らない。

```
[client]
default-character-set = utf8mb4
```

- [MySQL :: MySQL 5.6 リファレンスマニュアル :: 4.2.5 プログラムオプション修飾子](https://dev.mysql.com/doc/refman/5.6/ja/option-modifiers.html)
    - 特に `[client]` セクションにおいて、設定されているオプションを解釈できないクライアントが未知のパラメータを不正な記述と解釈して処理を止めてしまうことがある。そのような場合には `loose-` 接頭辞を付けることで回避できる。

```
[client]
loose-default-character-set = utf8mb4
```

- [MySQL :: MySQL 5.6 リファレンスマニュアル :: 10.1.4 接続文字セットおよび照合順序](https://dev.mysql.com/doc/refman/5.6/ja/charset-connection.html)

> クライアントはサーバーに接続するときに、使用する文字セットの名前を送信します。サーバーはこの名前を使用して、character_set_client、character_set_results、および character_set_connection システム変数を設定します。実際には、サーバーは文字セット名を使用して SET NAMES 操作を実行します。 

### (MySQL Charset) コネクション

サーバ側で `skip-character-set-client-handshake` が設定されている場合には、character_set_connection に関してはクライアントから送信された文字コードを無視する。

`skip-character-set-client-handshake` の設定は MySQL 4.0 の挙動に戻したい場合に使うものであるため、特別な理由がない限りはこの設定を行うべきではない。（参照：[MySQL :: MySQL 5.6 リファレンスマニュアル :: A.11 MySQL 5.6 FAQ: MySQL の中国語、日本語、および韓国語の文字セット](https://dev.mysql.com/doc/refman/5.6/ja/faqs-cjk.html#faq-cjk-how-use-4-0-charset)）

### (MySQL Charset) データベース

そのデータベース内の新規テーブルの Charset のデフォルト値として使用される。

- [MySQL :: MySQL 5.6 リファレンスマニュアル :: 13.1.10 CREATE DATABASE 構文](https://dev.mysql.com/doc/refman/5.6/ja/create-database.html)
- 作成：`CREATE DATABASE db_name CHARSET utf8mb4 COLLATE utf8mb4_bin;`
- 確認：`SHOW CREATE DATABASE db_name;`
- 変更：`ALTER DATABASE db_name CHARSET utf8mb4 COLLATE utf8mb4_bin;`

### (MySQL Charset) テーブル

そのテーブル内の新規カラムの Charset のデフォルト値として使用される。

- [MySQL :: MySQL 5.6 リファレンスマニュアル :: 13.1.17 CREATE TABLE 構文](https://dev.mysql.com/doc/refman/5.6/ja/create-table.html)
- [MySQL :: MySQL 5.6 リファレンスマニュアル :: 13.1.7 ALTER TABLE 構文](https://dev.mysql.com/doc/refman/5.6/ja/alter-table.html)
- 作成：`CREATE TABLE tbl_name (...) CHARSET utf8mb4 COLLATE utf8mb4_bin;`
- 確認：`SHOW CREATE TABLE tbl_name;`
- 変更：`ALTER TABLE tbl_name CHARSET utf8mb4 COLLATE utf8mb4_bin;`
- 既存カラムの変換：`ALTER TABLE tbl_name CONVERT TO CHARSET utf8mb4 COLLATE utf8mb4_bin;`

### (MySQL Charset) カラム

自身のカラムの Charset である。

- [MySQL :: MySQL 5.6 リファレンスマニュアル :: 13.1.17 CREATE TABLE 構文](https://dev.mysql.com/doc/refman/5.6/ja/create-table.html)
- 作成：`CREATE TABLE tbl_name (col_name VARCHAR(64) CHARSET utf8mb4 COLLATE utf8mb4_bin, ...);`
- 確認：`SHOW CREATE TABLE tbl_name;`
- 変更：`ALTER TABLE tbl_name MODIFY col_name VARCHAR(64) CHARSET utf8mb4 COLLATE utf8mb4_bin;`
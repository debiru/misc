# Redmine の導入

## Redmine をインストールする

```
sudo gem install rubygems-update
sudo update_rubygems
```

```
sudo apt install ruby-dev ruby bundler
sudo apt install libapache2-mod-passenger
sudo apt install imagemagick libmagick++-dev
sudo apt install git subversion
sudo apt install libmysqlclient-dev
```

- http://blog.redmine.jp/articles/3_3/install/ubuntu/
- http://redmine.jp/guide/RedmineInstall/

```
sudo apt install build-essential zlib1g-dev libssl-dev libreadline-dev libyaml-dev libcurl4-openssl-dev
sudo apt install apache2 apache2-dev libapr1-dev libaprutil1-dev
sudo apt install fonts-takao-pgothic
sudo gem install mysql2
sudo gem install passenger --no-rdoc --no-ri
```

## Redmine を設置する

```
cd /var/www/hosts/web/proj/public_html/
mkdir redmine/
cd redmine/
svn co http://svn.redmine.org/redmine/branches/3.3-stable .
```

```
cd /var/www/hosts/web/proj/public_html/redmine/
emacs config/database.yml
```

```
production:
  adapter: mysql2
  database: proj_database_name
  host: localhost
  username: mysqluser
  password: mysqluserpassword
  encoding: utf8mb4
  charset: utf8mb4
  collation: utf8mb4_bin
```

```
emacs config/configuration.yml
```

```
production:
  email_delivery:
    delivery_method: :smtp
    smtp_settings:
      address: example.org
      port: 25
      domain: proj.example.org

  rmagick_font_path: /usr/share/fonts/truetype/takao-gothic/TakaoPGothic.ttf
```

- utf8mb4 を設定する場合には schema_migrations でインデックスを張る処理が `Error: Index column size too large.` で失敗するため、このカラムサイズの上限を大きくする設定を行う

```
sudo emacs /etc/mysql/mysql.conf.d/mysqld.cnf
```

```
[mysqld]
innodb_file_format = Barracuda
innodb_file_per_table = 1
innodb_large_prefix
```

- `innodb_large_prefix` は、Engine が InnoDB であり ROW_FORMAT が DYNAMIC（または COMPRESSED）の場合に有効なので、ROW_FORMAT に DYNAMIC を使うようにする初期化スクリプトを追加する

```
emacs /var/www/hosts/web/proj/public_html/redmine/config/initializers/ar_innodb_row_format.rb
```

```
module InnodbRowFormat
  def create_table(table_name, options = {})
    table_options = options.merge(options: 'ENGINE=InnoDB ROW_FORMAT=DYNAMIC')
    super(table_name, table_options) do |td|
      yield td if block_given?
    end
  end
end

ActiveSupport.on_load :active_record do
  module ActiveRecord::ConnectionAdapters
    class AbstractMysqlAdapter
      prepend InnodbRowFormat
    end
  end
end
```

```
cd /var/www/hosts/web/proj/public_html/redmine/
sudo -u www-data bundle install --without development test --path vendor/bundle
```

```
# セッション改ざん防止用秘密鍵の作成
sudo -u www-data bundle exec rake generate_secret_token
# データベースのテーブル作成
sudo -u www-data RAILS_ENV=production bundle exec rake db:migrate
# デフォルトデータの登録
sudo -u www-data RAILS_ENV=production REDMINE_LANG=ja bundle exec rake redmine:load_default_data
```

データベース内にテーブルが作成されているか確認する。

- サブディレクトリとして Redmine を設置する場合には httpd.conf に `RackBaseURI` を設定する

`/apache2/sites-available/001-original.conf` の差分

```
 <VirtualHost *:80>
   ServerName proj.example.org
   VirtualDocumentRoot /var/www/hosts/web/proj/public_html
   Include includes/http.conf
   Include includes/log.conf
+  RackBaseURI /redmine
 </VirtualHost>
 
 <VirtualHost *:443>
   ServerName proj.example.org
   VirtualDocumentRoot /var/www/hosts/web/proj/public_html
   Include includes/https.conf
   Include includes/log.conf
+  RackBaseURI /redmine
 </VirtualHost>
```

```
sudo service apache2 restart
```

## Relay access denied を回避する

```
メール送信中にエラーが発生しました (454 4.7.1 <localpart@gmail.com>: Relay access denied )
```

Redmine のアカウント発行時に、`Relay access denied` が原因で新規登録ユーザのメールアドレスにメールが送信できない場合には、postfix の `mynetworks` にメールクライアント（Redmine）が動作しているサーバのホストを追加する。

`/etc/postfix/main.cf` の差分

```
-mynetworks = 127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128
+mynetworks = 127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128 $mydomain
```

```
sudo service postfix restart
```

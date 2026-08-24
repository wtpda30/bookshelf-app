# BookShelf 書籍レビューアプリ

## 概要

ユーザーは書籍を登録・閲覧し、レビューの投稿やお気に入り登録ができます。
ジャンルによる分類やレビューへのいいね機能、平均評価に基づくランキング機能も備えています。
外部アプリケーション向けの公開API(JSON)も提供します。

## 使用技術

- PHP 8.2
- Laravel 10.x
- MySQL 8.0
- Nginx
- Docker / Docker Compose / Laravel Sail
- Vite / Tailwind CSS 3.4
- Laravel Fortify（認証）
- phpMyAdmin

## ER図

![ER図](er.drawio.png)

## 環境開発URL

http://localhost

## 動作環境

- Docker
- Doker Compose

※Windowsの場合はWSL2の利用を推奨します。

## 環境構築手順

### 1. リポジトリをクローン

```
git clone https://github.com/wtpda30/bookshelf-app.git
```

### 2. .envファイルの準備

.env.example をコピーして .env を作成します。

`cp .env.example .env`

.env ファイル内の以下のDB接続情報を確認・設定します。.env.example のデフォルト値はSail向けではないため、以下のように変更してください。

```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

### 3. Composer依存パッケージのインストール

プロジェクトの初回セットアップ時は、vendor ディレクトリが存在しないため sail コマンドを使用できません。 以下のDockerコマンドを実行して、コンテナ内で composer install を実行します。

```
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
```

### 4.Laravel Sailの起動

以下のコマンドでDockerコンテナを起動します。

`./vendor/bin/sail up -d`

- エイリアスの設定（推奨）

    毎回 ./vendor/bin/sail と入力するのは手間なので、エイリアスを設定すると便利です。
    `alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'`

### 5. アプリケーションキーの生成

`sail artisan key:generate`

### 6. データベースのマイグレーションと初期データ投入

以下のコマンドでテーブルを作成し、ダミーデータを投入します。

`sail artisan migrate:fresh --seed`

### 7.フロントエンドのビルド

```
sail npm install
sail npm install alpinejs
sail npm run dev
```

`npm run dev`は開発中は起動したままにしてください。

### 8.アプリケーションへのアクセス

ブラウザで http://localhost にアクセスします。

## テスト実行

`sail artisan test`

カバレッジ付きで実行する場合

`sail artisan test --coverage`

## 機能一覧

- ユーザー認証（会員登録、ログイン、ログアウト）
- 書籍一覧画面(書籍登録・キーワード検索・ジャンルフィルタでの絞り込み・ソート順変更)
- 書籍詳細画面(書籍のお気に入り登録・レビュー投稿・レビューへのいいね・自分のレビューの編集削除、自分が登録した書籍の編集削除)
- 書籍登録画面(書籍登録・ISBN検索)
- 書籍編集画面(書籍の編集)
- レビュー編集
- お気に入り一覧表示
- ジャンル一覧表示(ジャンルの登録・編集・削除)
- ランキング表示
- マイ読書レポート(基本サマリー、評価分布、高評価書籍、ジャンル別評価傾向)
- 読書計画(作成・登録・編集・削除)
- 読書計画通知機能

## APIエンドポイント

認証不要の公開APIです。全エンドポイントは /api/v1 プレフィックス配下に定義されています。
| Method | URL | 概要 |
| :----- | :-----------------------| :------------------------------------------- |
| GET | /api/v1/books | 書籍一覧取得(検索・ジャンルでの絞り込み・ページネーション付き)|
| GET | /api/v1/books/{book} | 書籍詳詳細取得 |
| POST | /api/v1/books/ | 書籍の新規登録 |
| PUT | /api/v1/books/{book} | 書籍の更新 |
| DELETE | /api/v1/books/{book} | 書籍の削除 |

※POST、PUT、DELETEでSanctum APIトークン認証あり

## 作成者

宮古澄佳

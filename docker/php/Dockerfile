# =============================================================
# PHP 8.2 Dockerfile
# Laravel 11.x の実行環境を構築する
# ベースイメージに php:8.2-fpm を使用（FastCGI Process Manager）
# Nginx と PHP-FPM を連携させる構成
# =============================================================

FROM php:8.2-fpm

# -----------------------------------------------------------
# システムパッケージのインストール
# - git: Composerが依存関係をgitから取得する際に必要
# - curl: HTTPリクエスト・テスト用
# - libpng-dev / libjpeg-dev / libfreetype6-dev: GD拡張（画像処理）用
# - libonig-dev: mbstring拡張（マルチバイト文字の処理）用
# - libxml2-dev: xml / soap 拡張用
# - libsqlite3-dev: PHPUnit の in-memory SQLite 用
# - zip / unzip: Composerのパッケージ展開に必要
# -----------------------------------------------------------
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------
# PHP拡張のインストール
# - pdo_mysql: LaravelからMySQLに接続するために必要
# - pdo_sqlite: PHPUnit in-memory SQLite 用
# - mbstring: 日本語などマルチバイト文字列の処理に必要
# - exif: 画像のメタ情報処理（オプション）
# - pcntl: Queue Workerのシグナル処理（graceful shutdown）に必要
# - bcmath: 高精度な数値計算（金額計算で使用）
# - gd: 画像処理ライブラリ
# -----------------------------------------------------------
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd

# -----------------------------------------------------------
# Composerのインストール
# 公式イメージからバイナリをコピーする方法（推奨）
# -----------------------------------------------------------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# -----------------------------------------------------------
# 作業ディレクトリの設定
# Laravelプロジェクトを /var/www に配置する
# -----------------------------------------------------------
WORKDIR /var/www

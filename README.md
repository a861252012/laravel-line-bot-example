# Laravel LINE Bot Practice

Laravel 13 的 LINE Messaging API webhook 練習專案。收到 webhook 後會先驗證簽章、去重，再保存原始事件。

## 功能

- 使用未修改的 raw request body 驗證 LINE webhook 簽章。
- 用 `webhookEventId` 避免重複處理同一事件。
- 將事件內容保存到 `line_events`。
- 收到文字、圖片或位置訊息時，回覆一則確認訊息。

## 支援的訊息

| 類型 | 回覆 |
| --- | --- |
| 文字 | 已記錄文字訊息 |
| 圖片 | 已記錄圖片事件 |
| 位置 | 已記錄位置事件 |

## 安裝

需求：PHP 8.4、Composer 2、資料庫與 LINE Messaging API channel。

```bash
git clone https://github.com/a861252012/laravel-line-bot-practice.git
cd laravel-line-bot-practice
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

設定 `.env`：

```dotenv
LINE_CHANNEL_ACCESS_TOKEN=
LINE_CHANNEL_SECRET=
```

## LINE Webhook

到 LINE Developers Console 啟用 webhook，並將 URL 設為：

```text
https://你的網域/api/webhook
```

公開環境需要 HTTPS。本機開發可使用 tunnel 取得暫時網址。

## 測試

```bash
vendor/bin/pint --test
vendor/bin/phpunit
```

## License

MIT

# Laravel LINE Bot Practice

Laravel 13 的 LINE Messaging API 練習專案。收到 webhook 後會先驗證簽章，再處理 FAQ 與問題追蹤。

## 功能

- 驗證 LINE webhook 簽章。
- 用 `webhookEventId` 避免重複處理同一事件。
- 建立、查詢與關閉問題案件。
- 支援文字、Postback、圖片、位置與附件事件。

## 指令

| 輸入 | 功能 |
| --- | --- |
| `幫助` | 顯示操作選單 |
| `FAQ` | 顯示常見問題 |
| `問題 無法登入` | 建立問題案件 |
| `我的案件` | 查看自己的開啟中案件 |
| `關閉 #1` | 關閉自己的案件 |

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

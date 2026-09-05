# Laravel LINE Bot Example

[![Tests](https://github.com/a861252012/laravel-line-bot-example/actions/workflows/tests.yml/badge.svg)](https://github.com/a861252012/laravel-line-bot-example/actions/workflows/tests.yml)
[![Latest release](https://img.shields.io/github/v/release/a861252012/laravel-line-bot-example)](https://github.com/a861252012/laravel-line-bot-example/releases/latest)
[![License](https://img.shields.io/github/license/a861252012/laravel-line-bot-example)](LICENSE)

一個可直接延伸的 Laravel 13 LINE Messaging API 範例。它先把 webhook 安全、事件去重與簡單客服流程做好，再逐步擴充實際業務。

## 已實作功能

- 使用未修改的 raw request body 驗證 `x-line-signature`（HMAC-SHA256）。
- 以 `webhookEventId` 建立唯一索引，避免 LINE 重送事件時重複處理。
- 保存 `line_users`、`line_events` 與 `tickets`。
- FAQ Flex Message、Quick Reply、Postback 與 Rich Menu 設計稿。
- 文字指令建立、查詢、關閉案件。
- 圖片、位置與附件事件會保存原始 webhook 事件並回覆確認訊息。
- PHPUnit webhook 測試與 GitHub Actions CI。

## 指令示範

| 輸入或操作 | 結果 |
| --- | --- |
| `幫助` | 顯示 Quick Reply 選單 |
| `FAQ` | 顯示 Flex Message 常見問題 |
| `問題 無法登入` | 建立一筆開啟中的案件 |
| `我的案件` | 顯示最近 5 筆開啟中的案件 |
| `關閉 #1` | 關閉自己的案件 |

## 環境需求

- PHP 8.3 以上
- Composer 2
- MySQL、MariaDB 或 SQLite
- LINE Developers 的 Messaging API channel

`composer.lock` 會以 PHP 8.3 解析；CI 同時檢查 PHP 8.3 與 8.4。

## 安裝

```bash
git clone https://github.com/a861252012/laravel-line-bot-example.git
cd laravel-line-bot-example
composer install
cp .env.example .env
php artisan key:generate
```

設定 `.env` 的資料庫與 LINE 憑證：

```dotenv
LINE_CHANNEL_ACCESS_TOKEN=
LINE_CHANNEL_SECRET=
```

接著執行資料表 migration：

```bash
php artisan migrate
```

本機啟動：

```bash
php artisan serve
```

## LINE Developers 設定

1. 在 LINE Developers Console 建立或開啟 Messaging API channel。
2. 把 Channel access token 與 Channel secret 填入 `.env`；不要提交 `.env`。
3. 在 Messaging API 的 Webhook settings 設定：

   ```text
   https://你的網域/api/webhook
   ```

4. 開啟 webhook，並使用 Console 的 Verify 功能確認 endpoint 可收到請求。

公開網址必須是 HTTPS。若是本機開發，可使用受信任的 tunnel 服務提供暫時 HTTPS URL。

## Rich Menu

`resources/line/rich-menu.json` 是三個入口（FAQ、建立問題、我的案件）的 Rich Menu 設計稿。

LINE Rich Menu 還需要一張 2500 × 1686 的圖片。請透過 LINE Official Account Manager 或 LINE API 上傳圖片、建立 menu，並設定為預設 Rich Menu；本專案不會在部署時自動修改你的 LINE channel。

## 架構

```text
POST /api/webhook
  -> VerifyLineSignature
  -> LineBotController
  -> LineWebhookService
  -> LineWebhookRepository / LineMessagingService
```

Controller 只負責接收 webhook；Service 處理指令與回覆流程；Repository 集中資料庫讀寫。

## 測試與格式檢查

```bash
vendor/bin/phpunit
vendor/bin/pint --test
```

測試涵蓋有效與無效簽章、非文字事件、事件去重、使用者／案件建立，以及 LINE API 回覆失敗時的事件狀態。

## 目前刻意不做的事

- 不下載或 OCR 圖片、影片、音訊、檔案。
- 不加入 AI、支付或其他第三方服務。
- 不用 queue 延遲 reply；reply token 只能用一次且有時效，任何耗時工作應改為立即回覆後以 Queue 與 push message 接手。
- LINE API 回覆失敗的事件會標示為 `failed`，尚未提供自動重送機制。

## Release

[v0.1.1](https://github.com/a861252012/laravel-line-bot-example/releases/tag/v0.1.1) 是目前穩定版；[v0.1.0](https://github.com/a861252012/laravel-line-bot-example/releases/tag/v0.1.0) 是第一個公開版本。兩者都包含簽章驗證、事件去重、FAQ／工單流程、文件與 CI。

## License

MIT. See [LICENSE](LICENSE).

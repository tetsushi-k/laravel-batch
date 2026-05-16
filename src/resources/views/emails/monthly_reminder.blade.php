<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>月次お支払いリマインド</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Hiragino Sans', 'Yu Gothic UI', 'Meiryo', sans-serif;
            font-size: 14px;
            color: #374151;
            background-color: #f3f4f6;
            line-height: 1.6;
        }
        .wrapper {
            max-width: 600px;
            margin: 32px auto;
            padding: 0 16px;
        }
        .card {
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }
        /* ヘッダー */
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            color: #ffffff;
            padding: 36px 32px;
            text-align: center;
        }
        .header .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 9999px;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .header .month {
            font-size: 14px;
            opacity: 0.85;
            margin-top: 6px;
        }
        /* 本文 */
        .body {
            padding: 32px;
        }
        .greeting {
            font-size: 15px;
            margin-bottom: 20px;
        }
        /* アラートボックス */
        .alert {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            border-radius: 6px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .alert-title {
            font-weight: 700;
            color: #b91c1c;
            margin-bottom: 6px;
            font-size: 15px;
        }
        .alert-text {
            color: #6b7280;
            font-size: 13px;
        }
        /* 集計サマリー */
        .summary {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 28px;
        }
        .summary-title {
            font-size: 13px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 14px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .summary-row:last-child {
            border-bottom: none;
        }
        .summary-label {
            color: #6b7280;
            font-size: 13px;
        }
        .summary-value {
            font-weight: 700;
            color: #ef4444;
            font-size: 16px;
        }
        /* 注文テーブル */
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
            font-size: 13px;
        }
        .orders-table thead tr {
            background: #f9fafb;
        }
        .orders-table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }
        .orders-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }
        .orders-table tbody tr:hover {
            background: #fefefe;
        }
        .amount-cell {
            font-weight: 700;
            color: #dc2626;
        }
        .status-badge {
            display: inline-block;
            background: #fef2f2;
            color: #ef4444;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 9999px;
            border: 1px solid #fecaca;
        }
        /* 合計行 */
        .total-row td {
            background: #fff7ed;
            font-weight: 700;
            color: #c2410c;
            border-top: 2px solid #fed7aa;
            border-bottom: none !important;
        }
        /* CTAボタン */
        .cta-section {
            text-align: center;
            margin: 28px 0;
        }
        .cta-btn {
            display: inline-block;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 40px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.02em;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .note {
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
            margin-top: 10px;
        }
        /* フッター */
        .footer {
            background: #f9fafb;
            padding: 20px 32px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            font-size: 11px;
            color: #9ca3af;
            line-height: 1.8;
        }
        .footer a {
            color: #6b7280;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">

        {{-- ヘッダー --}}
        <div class="header">
            <div class="badge">PAYMENT REMINDER</div>
            <h1>お支払いリマインドのご連絡</h1>
            <div class="month">{{ $targetMonth }} 分</div>
        </div>

        {{-- 本文 --}}
        <div class="body">

            <p class="greeting">
                {{ $user->name }} 様<br><br>
                いつも {{ config('app.name') }} をご利用いただきありがとうございます。<br>
                {{ $targetMonth }} 分のご注文に、まだお支払いが完了していないものがございます。
            </p>

            {{-- アラート --}}
            <div class="alert">
                <div class="alert-title">⚠️ 未払いのご注文があります</div>
                <div class="alert-text">
                    下記の注文について、お早めにお支払い手続きをお願いいたします。<br>
                    お支払いが完了するまで、ご注文の処理が保留となる場合がございます。
                </div>
            </div>

            {{-- 集計サマリー --}}
            <div class="summary">
                <div class="summary-title">未払い注文サマリー</div>
                <div class="summary-row">
                    <span class="summary-label">対象月</span>
                    <span style="font-weight: 600;">{{ $targetMonth }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">未払い件数</span>
                    <span style="font-weight: 600;">{{ $unpaidOrders->count() }}件</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">未払い合計金額</span>
                    <span class="summary-value">
                        ¥{{ number_format($unpaidOrders->sum('amount')) }}
                    </span>
                </div>
            </div>

            {{-- 注文詳細テーブル --}}
            <div class="section-title">未払いご注文一覧</div>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>注文ID</th>
                        <th>ご注文日</th>
                        <th>金額</th>
                        <th>ステータス</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($unpaidOrders as $order)
                    <tr>
                        <td>#{{ $order->id }}</td>
                        <td>{{ $order->created_at->format('Y年m月d日') }}</td>
                        <td class="amount-cell">¥{{ number_format($order->amount) }}</td>
                        <td><span class="status-badge">未払い</span></td>
                    </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="2">合計</td>
                        <td>¥{{ number_format($unpaidOrders->sum('amount')) }}</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            {{-- CTAボタン --}}
            <div class="cta-section">
                <a href="{{ config('app.url') }}/orders" class="cta-btn">
                    今すぐお支払いへ
                </a>
                <p class="note">ボタンが機能しない場合は {{ config('app.url') }}/orders にアクセスしてください</p>
            </div>

            <p style="font-size: 13px; color: #6b7280;">
                ご不明な点やお心当たりのない場合は、誠にお手数ですがサポートまでご連絡ください。<br>
                今後ともよろしくお願いいたします。
            </p>

        </div>

        {{-- フッター --}}
        <div class="footer">
            <p>
                このメールは {{ config('app.name') }} から自動送信されています。<br>
                心当たりのない場合はこのメールを破棄してください。<br>
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </p>
        </div>

    </div>
</div>
</body>
</html>

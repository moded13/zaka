<?php
require_once 'bootstrap.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die('المعرّف غير صحيح');
}

$stmt = $pdo->prepare("
    SELECT 
        i.*,
        c.name AS category_name
    FROM finance_income i
    LEFT JOIN income_categories c ON i.category_id = c.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    die('الوصول غير موجود');
}

function amount_to_words_ar($number)
{
    return number_format((float)$number, 2) . ' فقط';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طباعة وصول إيراد</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #f6f8fb;
            margin: 0;
            padding: 20px;
            direction: rtl;
        }

        .print-bar {
            text-align: center;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            background: #163d7a;
            color: #fff;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 8px;
            margin: 0 5px;
        }

        .receipt {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #163d7a;
            border-radius: 14px;
            padding: 24px;
        }

        .header {
            text-align: center;
            margin-bottom: 24px;
        }

        .header h1 {
            margin: 0 0 10px;
            color: #163d7a;
            font-size: 30px;
        }

        .header h2 {
            margin: 0;
            color: #444;
            font-size: 20px;
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .box {
            border: 1px solid #dce3ee;
            border-radius: 10px;
            padding: 12px;
            background: #fafcff;
        }

        .label {
            font-weight: bold;
            color: #163d7a;
            margin-bottom: 6px;
        }

        .value {
            color: #222;
            font-size: 16px;
        }

        .main-amount {
            text-align: center;
            border: 2px dashed #118a7e;
            border-radius: 12px;
            padding: 18px;
            margin: 20px 0;
            background: #f3fffc;
        }

        .main-amount .amount {
            font-size: 34px;
            font-weight: bold;
            color: #118a7e;
            margin-bottom: 8px;
        }

        .notes {
            min-height: 90px;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .signature-box {
            text-align: center;
            padding-top: 20px;
        }

        .signature-line {
            border-top: 1px solid #777;
            margin-top: 40px;
            padding-top: 8px;
            color: #444;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .print-bar {
                display: none;
            }

            .receipt {
                border: 1px solid #000;
                border-radius: 0;
                max-width: 100%;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

<div class="print-bar">
    <button class="btn" onclick="window.print()">طباعة</button>
    <a class="btn" href="income.php">رجوع</a>
</div>

<div class="receipt">
    <div class="header">
        <h1>جمعية / لجنة</h1>
        <h2>وصول قبض</h2>
    </div>

    <div class="meta">
        <div class="box">
            <div class="label">رقم الوصول</div>
            <div class="value"><?= e($row['receipt_no']) ?></div>
        </div>

        <div class="box">
            <div class="label">التاريخ</div>
            <div class="value"><?= e($row['income_date']) ?></div>
        </div>

        <div class="box">
            <div class="label">اسم المتبرع</div>
            <div class="value"><?= e($row['donor_name']) ?></div>
        </div>

        <div class="box">
            <div class="label">نوع الإيراد</div>
            <div class="value"><?= e($row['category_name']) ?></div>
        </div>

        <div class="box">
            <div class="label">طريقة الدفع</div>
            <div class="value"><?= e($row['payment_method']) ?></div>
        </div>

        <div class="box">
            <div class="label">المبلغ كتابة</div>
            <div class="value"><?= e(amount_to_words_ar($row['amount'])) ?></div>
        </div>
    </div>

    <div class="main-amount">
        <div class="amount"><?= number_format((float)$row['amount'], 2) ?></div>
        <div>المبلغ المستلم</div>
    </div>

    <div class="box notes">
        <div class="label">ملاحظات</div>
        <div class="value"><?= nl2br(e($row['notes'])) ?></div>
    </div>

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line">توقيع المستلم</div>
        </div>

        <div class="signature-box">
            <div class="signature-line">توقيع أمين الصندوق</div>
        </div>
    </div>
</div>

</body>
</html>
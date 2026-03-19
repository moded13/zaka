# مركز الوثائق — Documents Center

وحدة مستقلة لرفع وإدارة وثائق جميع المستفيدين في نظام إدارة الزكاة.

## المسار
```
/zaka/documents/
```

## الملفات الرئيسية

| الملف | الوصف |
|-------|-------|
| `admin/index.php` | لوحة مركز الوثائق |
| `admin/beneficiary_documents.php` | رفع واستعراض وحذف وثائق مستفيد |
| `admin/ajax_beneficiaries.php` | AJAX: البحث عن مستفيد حسب النوع |
| `admin/document_upload_ajax.php` | AJAX: رفع ملف جديد (POST) |
| `admin/document_download.php` | تحميل/عرض ملف (GET) |
| `admin/document_delete.php` | حذف ملف (POST + CSRF) |
| `admin/document_viewer.php` | عرض سلايدر للوثائق |
| `includes/bootstrap.php` | تحميل الإعدادات والمكتبات |
| `includes/config.php` | مسارات التخزين والثوابت |
| `includes/helpers.php` | دوال مساعدة (رفع، CSRF، تخزين) |
| `includes/image.php` | معالجة الصور (GD) |
| `assets/style.css` | تنسيقات الواجهة |

## التخزين

الملفات المرفوعة تُحفظ في:
```
documents/zaka_storage/beneficiaries_docs/{beneficiary_id}/{filename}
```

## قاعدة البيانات

يستخدم جدول `beneficiary_documents` المشترك بين جميع أنواع المستفيدين:
- `id`, `beneficiary_id`, `doc_type`, `doc_side`, `title`
- `stored_path`, `thumb_path`, `original_name`, `mime_type`, `size_bytes`
- `sha256`, `uploaded_by_admin_id`, `is_shareable`, `created_at`

## الدخول

من لوحة الإدارة الرئيسية → بطاقة **مركز الوثائق**، أو مباشرةً عبر:
```
/zaka/documents/admin/index.php
```

أو من جدول المستفيدين → زر **وثائق (N)** بجانب كل مستفيد.

## الأمان

- تتطلب جميع الصفحات تسجيل الدخول (`requireLogin()`).
- جميع عمليات الكتابة محمية بـ CSRF.
- أنواع الملفات المسموح بها: `JPG`, `PNG`, `PDF`.
- الحد الأقصى لحجم الملف: **8 ميغابايت**.
- مسارات التحميل/الحذف محمية من Path Traversal.

## دمج PR

### عبر GitHub UI
1. اذهب إلى Pull Request في GitHub.
2. انقر **Merge pull request** ← **Confirm merge**.

### عبر CLI
```bash
git fetch origin
git checkout main
git merge origin/copilot/create-documents-center-ui
git push origin main
```

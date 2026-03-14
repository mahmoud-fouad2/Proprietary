<?php
declare(strict_types=1);

namespace Zaco\Core;

/**
 * FileUploader Service
 * Handles file uploads with proper MIME validation and security
 */
final class FileUploader
{
    /** @var array<string,array<string,string[]>> */
    private const ALLOWED_TYPES = [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            'mimes' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml',
            ],
        ],
        'document' => [
            'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'],
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'application/rtf',
            ],
        ],
        'archive' => [
            'extensions' => ['zip', 'rar', '7z', 'tar', 'gz'],
            'mimes' => [
                'application/zip',
                'application/x-zip-compressed',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
                'application/x-tar',
                'application/gzip',
            ],
        ],
        'software' => [
            'extensions' => ['exe', 'msi', 'dmg', 'pkg', 'deb', 'rpm', 'zip', 'rar', '7z'],
            'mimes' => [
                'application/x-msdownload',
                'application/x-msi',
                'application/x-apple-diskimage',
                'application/x-newton-compatible-pkg',
                'application/vnd.debian.binary-package',
                'application/x-rpm',
                'application/zip',
                'application/x-zip-compressed',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
                'application/octet-stream',
            ],
        ],
    ];

    private string $storageRoot;
    private int $maxFileSize;
    private string $lastError = '';

    public function __construct(?string $storageRoot = null, int $maxFileSizeMB = 50)
    {
        $this->storageRoot = $storageRoot ?? (dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage');
        $this->maxFileSize = $maxFileSizeMB * 1024 * 1024;
    }

    /**
     * Upload a file with validation
     * @param string $field Form field name
     * @param string $type Type of file (image, document, archive, software)
     * @param string $subdirectory Subdirectory within storage
     * @return array{success: bool, path?: string, filename?: string, error?: string}
     */
    public function upload(string $field, string $type, string $subdirectory = 'uploads'): array
    {
        $this->lastError = '';

        // Check if file was uploaded
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return ['success' => false, 'error' => 'لم يتم إرسال ملف'];
        }

        $file = $_FILES[$field];

        // Check upload error
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errorMessage = $this->getUploadErrorMessage((int)$file['error']);
            $this->lastError = $errorMessage;
            return ['success' => false, 'error' => $errorMessage];
        }

        // Check if file exists and is uploaded file
        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $this->lastError = 'ملف غير صالح';
            return ['success' => false, 'error' => 'ملف غير صالح'];
        }

        // Check file size
        $fileSize = (int)($file['size'] ?? 0);
        if ($fileSize <= 0 || $fileSize > $this->maxFileSize) {
            $maxMB = $this->maxFileSize / (1024 * 1024);
            $this->lastError = "حجم الملف يتجاوز الحد المسموح ({$maxMB}MB)";
            return ['success' => false, 'error' => $this->lastError];
        }

        // Get original filename and extension
        $originalName = (string)($file['name'] ?? '');
        $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Validate extension
        if (!$this->isAllowedExtension($extension, $type)) {
            $this->lastError = 'امتداد الملف غير مسموح';
            return ['success' => false, 'error' => 'امتداد الملف غير مسموح'];
        }

        // Validate MIME type
        $mimeValidation = $this->validateMimeType($tmpPath, $type, $extension);
        if (!$mimeValidation['valid']) {
            $this->lastError = $mimeValidation['error'];
            return ['success' => false, 'error' => $mimeValidation['error']];
        }

        // Additional image validation
        if ($type === 'image' && !$this->validateImage($tmpPath)) {
            $this->lastError = 'الملف ليس صورة صالحة';
            return ['success' => false, 'error' => 'الملف ليس صورة صالحة'];
        }

        // Generate unique filename
        $newFilename = $this->generateFilename($originalName, $extension);

        // Create target directory
        $targetDir = $this->storageRoot . DIRECTORY_SEPARATOR . trim($subdirectory, '/\\');
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true)) {
                $this->lastError = 'فشل في إنشاء مجلد التخزين';
                return ['success' => false, 'error' => 'فشل في إنشاء مجلد التخزين'];
            }
        }

        // Move uploaded file
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $newFilename;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            $this->lastError = 'فشل في حفظ الملف';
            return ['success' => false, 'error' => 'فشل في حفظ الملف'];
        }

        // Return success with relative path
        $relativePath = trim($subdirectory, '/\\') . '/' . $newFilename;

        return [
            'success' => true,
            'path' => $relativePath,
            'filename' => $newFilename,
            'original' => $originalName,
            'size' => $fileSize,
            'mime' => $mimeValidation['mime'] ?? '',
        ];
    }

    /**
     * Upload image from base64 data
     * @return array{success: bool, path?: string, filename?: string, error?: string}
     */
    public function uploadBase64(string $base64Data, string $subdirectory = 'uploads'): array
    {
        $this->lastError = '';

        // Parse base64 data
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $extension = mb_strtolower($matches[1]);
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        } else {
            $this->lastError = 'صيغة البيانات غير صحيحة';
            return ['success' => false, 'error' => $this->lastError];
        }

        // Validate extension
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $this->lastError = 'نوع الصورة غير مدعوم';
            return ['success' => false, 'error' => $this->lastError];
        }

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        // Decode base64
        $imageData = base64_decode($base64Data, true);
        if ($imageData === false) {
            $this->lastError = 'فشل في فك ترميز الصورة';
            return ['success' => false, 'error' => $this->lastError];
        }

        // Check size
        $size = strlen($imageData);
        if ($size > $this->maxFileSize) {
            $maxMB = $this->maxFileSize / (1024 * 1024);
            $this->lastError = "حجم الصورة يتجاوز الحد المسموح ({$maxMB}MB)";
            return ['success' => false, 'error' => $this->lastError];
        }

        // Validate it's actually an image
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false) {
            $this->lastError = 'البيانات ليست صورة صالحة';
            return ['success' => false, 'error' => $this->lastError];
        }

        // Generate filename
        $filename = $this->generateFilename('image', $extension);

        // Create target directory
        $targetDir = $this->storageRoot . DIRECTORY_SEPARATOR . trim($subdirectory, '/\\');
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true)) {
                $this->lastError = 'فشل في إنشاء مجلد التخزين';
                return ['success' => false, 'error' => $this->lastError];
            }
        }

        // Save file
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($targetPath, $imageData) === false) {
            $this->lastError = 'فشل في حفظ الصورة';
            return ['success' => false, 'error' => $this->lastError];
        }

        $relativePath = trim($subdirectory, '/\\') . '/' . $filename;

        return [
            'success' => true,
            'path' => $relativePath,
            'filename' => $filename,
            'size' => $size,
            'mime' => $imageInfo['mime'] ?? 'image/' . $extension,
        ];
    }

    /**
     * Check if a file was uploaded (exists in request)
     */
    public function hasFile(string $field): bool
    {
        return isset($_FILES[$field])
            && is_array($_FILES[$field])
            && (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            && !empty($_FILES[$field]['tmp_name']);
    }

    /**
     * Delete a file from storage
     */
    public function delete(string $relativePath): bool
    {
        $fullPath = $this->storageRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\');
        
        if (!is_file($fullPath)) {
            return true; // Already doesn't exist
        }

        // Security check - ensure path is within storage root
        $realPath = realpath($fullPath);
        $realStorageRoot = realpath($this->storageRoot);
        
        if ($realPath === false || $realStorageRoot === false) {
            return false;
        }

        if (!str_starts_with($realPath, $realStorageRoot)) {
            return false; // Path traversal attempt
        }

        return @unlink($fullPath);
    }

    /**
     * Get the last error message
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Check if extension is allowed for the given type
     */
    private function isAllowedExtension(string $extension, string $type): bool
    {
        $allowed = self::ALLOWED_TYPES[$type]['extensions'] ?? [];
        return in_array($extension, $allowed, true);
    }

    /**
     * Validate MIME type using finfo
     * @return array{valid: bool, mime?: string, error?: string}
     */
    private function validateMimeType(string $filePath, string $type, string $extension): array
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($filePath);

        if ($detectedMime === false) {
            return ['valid' => false, 'error' => 'فشل في تحديد نوع الملف'];
        }

        $allowedMimes = self::ALLOWED_TYPES[$type]['mimes'] ?? [];

        // For some file types, MIME detection can be unreliable
        // So we also check common mismatches
        $mimeToExtension = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'image/svg+xml' => ['svg'],
            'application/pdf' => ['pdf'],
            'text/plain' => ['txt', 'csv', 'log'],
            'application/octet-stream' => ['exe', 'msi', 'dmg', '7z', 'rar', 'zip'], // Generic binary
        ];

        // Check if MIME is in allowed list
        if (in_array($detectedMime, $allowedMimes, true)) {
            return ['valid' => true, 'mime' => $detectedMime];
        }

        // For software/archives, application/octet-stream is common
        if (in_array($type, ['software', 'archive'], true) && $detectedMime === 'application/octet-stream') {
            return ['valid' => true, 'mime' => $detectedMime];
        }

        // Check extension-MIME match for edge cases
        if (isset($mimeToExtension[$detectedMime])) {
            if (in_array($extension, $mimeToExtension[$detectedMime], true)) {
                return ['valid' => true, 'mime' => $detectedMime];
            }
        }

        return [
            'valid' => false,
            'error' => 'نوع الملف الفعلي (' . $detectedMime . ') لا يتطابق مع الامتداد',
        ];
    }

    /**
     * Validate that file is actually an image
     */
    private function validateImage(string $filePath): bool
    {
        // Use getimagesize which properly parses image headers
        $imageInfo = @getimagesize($filePath);
        
        if ($imageInfo === false) {
            return false;
        }

        // Check dimensions are reasonable
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);

        if ($width <= 0 || $height <= 0) {
            return false;
        }

        // Check for reasonable max dimensions (prevent decompression bombs)
        $maxDimension = 10000;
        if ($width > $maxDimension || $height > $maxDimension) {
            return false;
        }

        return true;
    }

    /**
     * Generate a unique filename
     */
    private function generateFilename(string $originalName, string $extension): string
    {
        // Clean original name for use in filename
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $baseName);
        $baseName = mb_substr((string)$baseName, 0, 30);

        if ($baseName === '') {
            $baseName = 'file';
        }

        // Generate unique suffix
        $timestamp = date('YmdHis');
        $random = bin2hex(random_bytes(4));

        return "{$timestamp}_{$random}_{$baseName}.{$extension}";
    }

    /**
     * Convert PHP upload error code to human-readable message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'حجم الملف يتجاوز الحد المسموح في إعدادات PHP',
            UPLOAD_ERR_FORM_SIZE => 'حجم الملف يتجاوز الحد المحدد في النموذج',
            UPLOAD_ERR_PARTIAL => 'تم رفع جزء من الملف فقط',
            UPLOAD_ERR_NO_FILE => 'لم يتم اختيار ملف',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد الملفات المؤقتة غير موجود',
            UPLOAD_ERR_CANT_WRITE => 'فشل في كتابة الملف',
            UPLOAD_ERR_EXTENSION => 'تم إيقاف الرفع بواسطة إضافة PHP',
            default => 'خطأ غير معروف في رفع الملف',
        };
    }
}

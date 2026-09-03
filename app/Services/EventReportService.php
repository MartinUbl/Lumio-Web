<?php
declare(strict_types=1);

namespace App\Model\Services;

use App\Model\Entities\Event;
use App\Model\Reports\EventReportData;
use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Utils\Random;
use RuntimeException;

final class EventReportService
{
    private const int MAX_FILE_SIZE = 10 * 1024 * 1024;
    private const int MAX_IMAGE_COUNT = 10;
    private const string REPORTS_DIR = 'reports';
    private const string IMAGES_DIR = 'images';
    private const string MANIFEST_FILE = 'manifest.json';
    private const string PDF_FILE_NAME = 'report.pdf';

    /** @var array<string, string> */
    private const array IMAGE_MIME_MAP = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @var string[] */
    private const array PDF_MIME_TYPES = [
        'application/pdf',
    ];

    public function __construct(
        private readonly string $uploadDir,
    ) {
    }

    public function loadReport(?string $eventReportPath): ?EventReportData
    {
        $relativePath = $this->normalizeRelativePath($eventReportPath);
        if ($relativePath === null) {
            return null;
        }

        if ($this->isPdfPath($relativePath)) {
            return new EventReportData(
                directory: '',
                pdfEntry: $relativePath,
                legacyPdf: true,
            );
        }

        $reportDir = $this->resolveUploadPath($relativePath);
        if (!is_dir($reportDir)) {
            return null;
        }

        $manifestPath = $reportDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
        if (!is_file($manifestPath)) {
            return null;
        }

        try {
            /** @var array{text?: mixed, pdf?: mixed, images?: mixed} $manifest */
            $manifest = Json::decode((string) file_get_contents($manifestPath), Json::FORCE_ARRAY);
        } catch (JsonException) {
            return null;
        }

        $text = trim((string) ($manifest['text'] ?? ''));
        $pdfEntry = $this->normalizeManifestEntry($manifest['pdf'] ?? null, true);
        $imageEntries = [];

        $rawImages = $manifest['images'] ?? [];
        if (is_array($rawImages)) {
            foreach ($rawImages as $imageEntry) {
                $normalized = $this->normalizeManifestEntry($imageEntry, false);
                if ($normalized !== null) {
                    $imageEntries[] = $normalized;
                }
            }
        }

        $imageEntries = array_values(array_filter($imageEntries, function (string $entry) use ($relativePath): bool {
            return is_file($this->resolveUploadPath($relativePath . '/' . $entry));
        }));

        if ($pdfEntry !== null && !is_file($this->resolveUploadPath($relativePath . '/' . $pdfEntry))) {
            $pdfEntry = null;
        }

        $report = new EventReportData(
            directory: $relativePath,
            text: $text,
            pdfEntry: $pdfEntry,
            imageEntries: array_values(array_unique($imageEntries)),
        );

        return $report->hasContent() ? $report : null;
    }

    public function saveReport(
        Event $event,
        ?string $text,
        ?FileUpload $pdfUpload,
        array $imageUploads,
        bool $removePdf = false,
        array $removeImages = [],
    ): EventReportData {
        if ($event->id === null) {
            throw new RuntimeException('K reportu nelze přiřadit neuloženou akci.');
        }

        $reportDirectory = $this->getReportDirectoryRelative($event->id);
        $absoluteReportDirectory = $this->resolveUploadPath($reportDirectory);
        $absoluteImagesDirectory = $absoluteReportDirectory . DIRECTORY_SEPARATOR . self::IMAGES_DIR;

        $existingReport = $this->loadReport($event->eventReportPath);
        $existingImageEntries = $existingReport?->imageEntries ?? [];
        $existingPdfEntry = $existingReport?->legacyPdf === true
            ? null
            : ($existingReport?->pdfEntry ?? null);
        $legacyPdfSource = null;
        if ($existingReport?->legacyPdf === true && $existingReport->pdfEntry !== null) {
            $legacySource = $this->resolveUploadPath($existingReport->pdfEntry);
            if (is_file($legacySource)) {
                $existingPdfEntry = self::PDF_FILE_NAME;
                $legacyPdfSource = $legacySource;
            }
        }

        $normalizedText = trim((string) $text);
        $removeImages = array_values(array_unique(array_filter(array_map(
            fn(mixed $entry): ?string => $this->normalizeManifestEntry($entry, false),
            $removeImages,
        ))));

        foreach ($removeImages as $removeImage) {
            if (!in_array($removeImage, $existingImageEntries, true)) {
                throw new RuntimeException('Nepodařilo se ověřit obrázky označené k odstranění.');
            }
        }

        $uploads = array_values(array_filter(
            $imageUploads,
            static fn(mixed $upload): bool => $upload instanceof FileUpload && $upload->hasFile(),
        ));

        if (count($uploads) > self::MAX_IMAGE_COUNT) {
            throw new RuntimeException('Najednou můžeš nahrát maximálně 10 obrázků.');
        }

        $newImageEntries = [];
        $validatedImageExtensions = [];
        foreach ($uploads as $upload) {
            $validatedImageExtensions[] = $this->validateImageUpload($upload);
        }

        $finalImageEntries = array_values(array_filter(
            $existingImageEntries,
            static fn(string $entry): bool => !in_array($entry, $removeImages, true),
        ));

        foreach ($validatedImageExtensions as $extension) {
            $newImageEntries[] = self::IMAGES_DIR . '/' . Random::generate(16) . '.' . $extension;
        }

        $finalImageEntries = array_values(array_merge($finalImageEntries, $newImageEntries));

        if (count($finalImageEntries) > self::MAX_IMAGE_COUNT) {
            throw new RuntimeException('Report může obsahovat maximálně 10 obrázků.');
        }

        $finalPdfEntry = $existingPdfEntry;
        if ($removePdf) {
            $finalPdfEntry = null;
        }

        $hasNewPdfUpload = $pdfUpload instanceof FileUpload && $pdfUpload->hasFile();
        if ($hasNewPdfUpload) {
            $this->validatePdfUpload($pdfUpload);
        }

        $finalPdfWillExist = ($finalPdfEntry !== null && !$removePdf) || $hasNewPdfUpload;

        if ($normalizedText === '' && !$finalPdfWillExist && $finalImageEntries === []) {
            throw new RuntimeException('Vyplň alespoň text reportu, nahraj PDF nebo přidej alespoň jeden obrázek.');
        }

        FileSystem::createDir($absoluteReportDirectory);
        FileSystem::createDir($absoluteImagesDirectory);

        foreach ($removeImages as $removeImage) {
            $this->deleteFileIfExists($absoluteReportDirectory, $removeImage);
        }

        if ($removePdf && $existingPdfEntry !== null) {
            $this->deleteFileIfExists($absoluteReportDirectory, $existingPdfEntry);
        }

        if ($legacyPdfSource !== null && !$removePdf && !$hasNewPdfUpload && !is_file($absoluteReportDirectory . DIRECTORY_SEPARATOR . self::PDF_FILE_NAME)) {
            copy($legacyPdfSource, $absoluteReportDirectory . DIRECTORY_SEPARATOR . self::PDF_FILE_NAME);
        }

        foreach ($uploads as $index => $upload) {
            $upload->move(
                $absoluteReportDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newImageEntries[$index])
            );
        }

        if ($hasNewPdfUpload) {
            $pdfUpload->move($absoluteReportDirectory . DIRECTORY_SEPARATOR . self::PDF_FILE_NAME);
            $finalPdfEntry = self::PDF_FILE_NAME;
        }

        $report = new EventReportData(
            directory: $reportDirectory,
            text: $normalizedText,
            pdfEntry: $finalPdfEntry,
            imageEntries: $finalImageEntries,
        );

        file_put_contents(
            $absoluteReportDirectory . DIRECTORY_SEPARATOR . self::MANIFEST_FILE,
            Json::encode($report->toManifest(), true)
        );

        return $report;
    }

    public function getReportDirectoryRelative(int $eventId): string
    {
        return self::REPORTS_DIR . '/event-' . $eventId;
    }

    private function validatePdfUpload(FileUpload $upload): void
    {
        if (!$upload->isOk()) {
            throw new RuntimeException('PDF report se nepodařilo nahrát.');
        }

        if (($upload->getSize() ?? 0) > self::MAX_FILE_SIZE) {
            throw new RuntimeException('PDF report může mít maximálně 10 MB.');
        }

        $extension = strtolower(pathinfo($this->getUploadClientName($upload), PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            throw new RuntimeException('PDF report musí být ve formátu PDF.');
        }

        $mime = $this->detectMimeType($upload);
        if (!in_array($mime, self::PDF_MIME_TYPES, true)) {
            throw new RuntimeException('PDF report musí být ve formátu PDF.');
        }
    }

    private function validateImageUpload(FileUpload $upload): string
    {
        if (!$upload->isOk()) {
            throw new RuntimeException('Jeden z obrázků se nepodařilo nahrát.');
        }

        if (($upload->getSize() ?? 0) > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Každý obrázek může mít maximálně 10 MB.');
        }

        $mime = $this->detectMimeType($upload);
        $extension = self::IMAGE_MIME_MAP[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Povolené jsou jen obrázky JPG, PNG nebo WEBP.');
        }

        $clientExtension = strtolower(pathinfo($this->getUploadClientName($upload), PATHINFO_EXTENSION));
        if (!in_array($clientExtension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Povolené jsou jen obrázky JPG, PNG nebo WEBP.');
        }

        return $extension;
    }

    private function detectMimeType(FileUpload $upload): string
    {
        $temporaryFile = $upload->getTemporaryFile();
        if ($temporaryFile !== '' && is_file($temporaryFile) && class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($temporaryFile);

            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }

        return strtolower((string) $upload->getContentType());
    }

    private function getUploadClientName(FileUpload $upload): string
    {
        return method_exists($upload, 'getUntrustedName')
            ? (string) $upload->getUntrustedName()
            : '';
    }

    private function deleteFileIfExists(string $absoluteReportDirectory, string $relativeEntry): void
    {
        $filePath = $absoluteReportDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeEntry);
        $resolvedBase = realpath($absoluteReportDirectory);
        $resolvedPath = realpath($filePath);

        if ($resolvedPath === false || $resolvedBase === false) {
            return;
        }

        if (!str_starts_with($resolvedPath, $resolvedBase . DIRECTORY_SEPARATOR) && $resolvedPath !== $resolvedBase) {
            throw new RuntimeException('Nepovolená operace se soubory reportu.');
        }

        if (is_file($resolvedPath)) {
            @unlink($resolvedPath);
        }
    }

    private function normalizeManifestEntry(mixed $entry, bool $allowRootFile): ?string
    {
        if (!is_string($entry)) {
            return null;
        }

        $entry = trim(str_replace('\\', '/', $entry));
        if ($entry === '' || str_contains($entry, '..') || str_starts_with($entry, '/')) {
            return null;
        }

        if (!$allowRootFile && !str_starts_with($entry, self::IMAGES_DIR . '/')) {
            return null;
        }

        return $entry;
    }

    private function normalizeRelativePath(?string $relativePath): ?string
    {
        if ($relativePath === null) {
            return null;
        }

        $relativePath = trim(str_replace('\\', '/', $relativePath));
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            return null;
        }

        return $relativePath;
    }

    private function resolveUploadPath(string $relativePath): string
    {
        $relativePath = str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        return rtrim($this->uploadDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\');
    }

    private function isPdfPath(string $relativePath): bool
    {
        return str_ends_with(strtolower($relativePath), '.pdf');
    }
}

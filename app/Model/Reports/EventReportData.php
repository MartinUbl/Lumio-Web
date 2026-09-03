<?php
declare(strict_types=1);

namespace App\Model\Reports;

final class EventReportData
{
    /**
     * @param string[] $imageEntries
     */
    public function __construct(
        public readonly string $directory,
        public readonly string $text = '',
        public readonly ?string $pdfEntry = null,
        public readonly array $imageEntries = [],
        public readonly bool $legacyPdf = false,
    ) {
    }

    public function hasContent(): bool
    {
        return $this->text !== '' || $this->pdfEntry !== null || $this->imageEntries !== [];
    }

    public function getPdfRelativePath(): ?string
    {
        if ($this->pdfEntry === null) {
            return null;
        }

        if ($this->legacyPdf || $this->directory === '') {
            return $this->pdfEntry;
        }

        return $this->directory . '/' . $this->pdfEntry;
    }

    /**
     * @return string[]
     */
    public function getImageRelativePaths(): array
    {
        if ($this->directory === '') {
            return [];
        }

        return array_values(array_map(
            fn(string $entry): string => $this->directory . '/' . $entry,
            $this->imageEntries,
        ));
    }

    /**
     * @return array<string, string>
     */
    public function getImageOptions(): array
    {
        $options = [];
        foreach ($this->imageEntries as $index => $entry) {
            $options[$entry] = 'Obrázek ' . ($index + 1) . ' (' . basename($entry) . ')';
        }

        return $options;
    }

    /**
     * @return array{text: string, pdf: ?string, images: string[]}
     */
    public function toManifest(): array
    {
        return [
            'text' => $this->text,
            'pdf' => $this->pdfEntry,
            'images' => array_values($this->imageEntries),
        ];
    }
}

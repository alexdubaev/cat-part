<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Минимальный потоковый читатель XLSX — без Composer и PhpSpreadsheet.
 *
 * Зачем свой: на шаред-хостинге ставить зависимости негде, а выгрузка приходит в .xlsx.
 * XLSX — это ZIP с XML внутри, читаем его напрямую:
 *   xl/sharedStrings.xml      — словарь строк (Excel выносит текст туда)
 *   xl/worksheets/sheet1.xml  — сами ячейки, в порядке строк
 *
 * Строки отдаём генератором: лист не грузится в память целиком (22 000 строк с описаниями
 * иначе не влезут). В память попадает только словарь строк — это неизбежно, зато один раз.
 *
 * Поддержано ровно то, что встречается в выгрузках: t="s" (словарь), t="inlineStr",
 * t="str" (результат формулы), числа. Формат/стили игнорируем — нам нужен текст.
 */
final class XlsxReader
{
    private string $path;
    /** @var string[] словарь общих строк */
    private array $shared = [];
    private bool $sharedLoaded = false;

    public function __construct(string $path)
    {
        if (!is_file($path)) {
            throw new RuntimeException("Файл не найден: $path");
        }
        $this->path = $path;
    }

    /** Первая строка листа — заголовки колонок. */
    public function header(): array
    {
        foreach ($this->rows() as $row) {
            return array_map(static fn($v) => trim((string) $v), $row);
        }
        return [];
    }

    /**
     * Итерация по строкам листа. Первая строка — заголовок (её отдаём тоже).
     *
     * @param int $skipRows сколько строк листа пропустить (для докачки пачками)
     * @return \Generator<int,array<int,string>>
     */
    public function rows(int $skipRows = 0): \Generator
    {
        $this->loadShared();

        $zip = new ZipArchive();
        if ($zip->open($this->path) !== true) {
            throw new RuntimeException("Не открывается XLSX: {$this->path}");
        }
        $sheetName = $this->firstSheetName($zip);
        $zip->close();

        // Читаем лист потоком через обёртку zip:// — лист целиком в память не поднимаем.
        $xml = new XMLReader();
        if (!@$xml->open('zip://' . $this->path . '#' . $sheetName)) {
            throw new RuntimeException("Не читается лист $sheetName в {$this->path}");
        }

        $rowIndex = 0;
        while ($xml->read()) {
            if ($xml->nodeType !== XMLReader::ELEMENT || $xml->name !== 'row') {
                continue;
            }
            $rowIndex++;
            $node = $xml->readOuterXml();
            if ($rowIndex <= $skipRows) {
                continue;
            }
            $cells = $this->parseRow($node);
            if ($cells === []) {
                continue;
            }
            yield $rowIndex => $cells;
        }

        $xml->close();
    }

    /** Доступно ли чтение XLSX на этом сервере (нужны расширения zip и xml). */
    public static function isSupported(): bool
    {
        return class_exists('ZipArchive') && class_exists('XMLReader');
    }

    /** Разбор одной <row> в плоский массив по индексам колонок (A→0, B→1, …). */
    private function parseRow(string $xmlRow): array
    {
        if (!preg_match_all('/<c\b([^>]*)(?:\/>|>(.*?)<\/c>)/s', $xmlRow, $m, PREG_SET_ORDER)) {
            return [];
        }
        $out = [];
        $max = -1;
        foreach ($m as $cell) {
            $attrs = $cell[1];
            $inner = $cell[2] ?? '';

            $col = 0;
            if (preg_match('/r="([A-Z]+)\d+"/', $attrs, $rm)) {
                $col = self::colToIndex($rm[1]);
            }
            $type = preg_match('/t="([^"]+)"/', $attrs, $tm) ? $tm[1] : 'n';

            $value = '';
            if ($type === 'inlineStr') {
                $value = self::textOf($inner);
            } elseif (preg_match('/<v>(.*?)<\/v>/s', $inner, $vm)) {
                $raw = html_entity_decode($vm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                $value = $type === 's' ? ($this->shared[(int) $raw] ?? '') : $raw;
            }

            $out[$col] = $value;
            $max = max($max, $col);
        }
        if ($max < 0) {
            return [];
        }
        // Разрежённые строки: Excel не пишет пустые ячейки — добиваем пустыми.
        $dense = [];
        for ($i = 0; $i <= $max; $i++) {
            $dense[$i] = $out[$i] ?? '';
        }
        return $dense;
    }

    /** Словарь общих строк: <si> может состоять из нескольких <t> (форматированные куски). */
    private function loadShared(): void
    {
        if ($this->sharedLoaded) {
            return;
        }
        $this->sharedLoaded = true;

        $zip = new ZipArchive();
        if ($zip->open($this->path) !== true) {
            throw new RuntimeException("Не открывается XLSX: {$this->path}");
        }
        $has = $zip->locateName('xl/sharedStrings.xml') !== false;
        $zip->close();
        if (!$has) {
            return;
        }

        $xml = new XMLReader();
        $xml->open('zip://' . $this->path . '#xl/sharedStrings.xml');
        while ($xml->read()) {
            if ($xml->nodeType === XMLReader::ELEMENT && $xml->name === 'si') {
                $this->shared[] = self::textOf($xml->readInnerXml());
            }
        }
        $xml->close();
    }

    /** Все <t> внутри узла, склеенные подряд. */
    private static function textOf(string $xml): string
    {
        if (!preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $xml, $m)) {
            return '';
        }
        return html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Имя файла первого листа в архиве. */
    private function firstSheetName(ZipArchive $zip): string
    {
        // Обычно xl/worksheets/sheet1.xml, но бывает иначе — ищем первый по алфавиту.
        $candidates = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $candidates[] = $name;
            }
        }
        if (!$candidates) {
            throw new RuntimeException('В XLSX не найден лист');
        }
        natsort($candidates);
        return (string) reset($candidates);
    }

    /** 'A'→0, 'B'→1, 'AA'→26 */
    private static function colToIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }
}

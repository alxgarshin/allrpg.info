<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Fraym\Element\Item;

use Fraym\Element\Attribute as Attribute;
use Fraym\Element\Item\Trait\CloneTrait;
use Fraym\Helper\DataHelper;
use Fraym\Interface\ElementAttribute;

/** Файл */
class File extends BaseElement
{
    use CloneTrait;

    /** Значение */
    private ?string $fieldValue = null;

    private Attribute\File $attribute;

    public function usualAsHTMLRenderer(bool $editableFormat, bool $removeHtmlFromValue = false): string
    {
        $html = '';
        $value = $this->get();
        $name = $this->name . $this->getLineNumberWrapped();
        $uploadNum = $this->getUploadNum();
        $uploadData = $this->getUploadData();
        $uploadedFiles = [];

        if ($value) {
            preg_match_all('#{([^:]+):([^}]+)}#', $value, $matches);

            foreach ($matches[0] as $key => $value) {
                if (!$_ENV['HIDE_NON_EXISTING_FILES'] || file_exists($_ENV['UPLOADS_PATH'] . $uploadData['path'] . $matches[2][$key])) {
                    $uploadedFiles[] = [
                        'name_shown' => $matches[1][$key],
                        'name' => $matches[2][$key],
                        'path' => $uploadData['path'] . $matches[2][$key],
                    ];
                }
            }
        }

        if ($editableFormat) {
            $html .= '
<input type="file" id="' . $name . '" name="' . $name . (($uploadData['multiple'] ?? false) ? '[]' : '') . '" class="inputfile' . $this->getObligatoryStr() . '" data-upload-path="' . $_ENV['UPLOADS_PATH'] . '" data-upload-num="' . $uploadNum . '" data-upload-name="' . $uploadData['columnname'] . '"' . (($uploadData['multiple'] ?? false) ? ' multiple' : '') . ($uploadedFiles ? ' data-uploaded-files=\'' . DataHelper::jsonFixedEncode($uploadedFiles) . '\'' : '') . (($uploadData['isimage'] ?? false) ? ' accept="image/*"' : '') . ' />';
        } else {
            foreach ($uploadedFiles as $key => $value) {
                $html .= '<div class="uploaded_file"><a href="' . $_ENV['UPLOADS_PATH'] . $value['path'] . '" target="_blank">' . $value['name'] . '</a></div>';
            }
        }

        return $html;
    }

    public function asArray(): array
    {
        return array_merge(
            [
                'fieldValue' => $this->get(),
                'uploadNum' => $this->getUploadNum(),
                'uploadData' => $this->getUploadData(),
                'baseUploadData' => $this->getUploadData(0),
            ],
            $this->asArrayBase(),
        );
    }

    public function getAttribute(): Attribute\File
    {
        return $this->attribute;
    }

    public function setAttribute(ElementAttribute $attribute, bool $skipAttributeCheck = false): static
    {
        if (!$skipAttributeCheck) {
            $this->checkAttribute($attribute, Attribute\File::class);
        }
        /** @var Attribute\File $attribute */
        $this->attribute = $attribute;

        return $this;
    }

    public function getDefaultValue(): mixed
    {
        return null;
    }

    public function get(): ?string
    {
        if (!isset($this->fieldValue)) {
            $pureValue = $this->model?->getModelDataFieldValue($this->name);

            if (!is_null($pureValue) && trim($pureValue) !== '') {
                $this->fieldValue = trim($pureValue);
            } else {
                $this->fieldValue = null;
            }
        }

        return $this->fieldValue;
    }

    public function set(?string $fieldValue): static
    {
        $this->fieldValue = $fieldValue;

        return $this;
    }

    public function getUploadNum(): ?int
    {
        return $this->getAttribute()->getUploadNum();
    }

    public function getUploadData(?int $uploadNum = null): ?array
    {
        $uploadNum = $uploadNum ?? $this->getUploadNum();

        return $_ENV['UPLOADS'][$uploadNum];
    }

    public function remove(string $fileName): void
    {
        $upload = $this->getUploadData();
        $filesDirectory = INNER_PATH . 'public' . $_ENV['UPLOADS_PATH'] . $upload['path'];

        $fileName = basename($fileName);

        if (file_exists($filesDirectory . $fileName)) {
            unlink($filesDirectory . $fileName);
        }

        if (file_exists($filesDirectory . 'thumbnail/' . $fileName)) {
            unlink($filesDirectory . 'thumbnail/' . $fileName);
        }
    }
}

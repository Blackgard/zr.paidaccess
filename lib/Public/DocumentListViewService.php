<?php

namespace Zr\PaidAccess\PublicUi;

use Zr\PaidAccess\Document\RequiredDocumentService;
use Zr\PaidAccess\PaidAccessCore;

class DocumentListViewService
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function buildViewModel(array $params = []): array
    {
        $siteId = PaidAccessCore::normalizeSiteId($params['SITE_ID'] ?? null);
        $onlyRequired = self::isYes($params['ONLY_REQUIRED'] ?? 'N');
        $detailUrlTemplate = trim((string)($params['DETAIL_URL'] ?? ''));
        $showHeader = !isset($params['SHOW_HEADER']) || self::isYes($params['SHOW_HEADER']);
        $headerTitle = trim((string)($params['HEADER_TITLE'] ?? ''));
        if ($headerTitle === '') {
            $headerTitle = 'Наименование документа';
        }

        $codeFilter = trim((string)($params['CODE'] ?? ''));
        if ($codeFilter !== '') {
            $item = RequiredDocumentService::getByCode($codeFilter, $siteId, $detailUrlTemplate);
            $items = $item !== null ? [$item] : [];
        } else {
            $items = RequiredDocumentService::getPublishedList($siteId, $onlyRequired);
            if ($detailUrlTemplate !== '') {
                $items = array_map(
                    static function (array $item) use ($detailUrlTemplate): array {
                        $item['URL'] = RequiredDocumentService::resolvePublicUrl($item, $detailUrlTemplate);
                        $item['OPEN_IN_NEW_TAB'] = !empty($item['HAS_FILE']);

                        return $item;
                    },
                    $items
                );
            }
        }

        return [
            'SITE_ID' => $siteId,
            'ITEMS' => $items,
            'SHOW_HEADER' => $showHeader,
            'HEADER_TITLE' => $headerTitle,
        ];
    }

    private static function isYes($value): bool
    {
        return (string)$value === 'Y';
    }
}

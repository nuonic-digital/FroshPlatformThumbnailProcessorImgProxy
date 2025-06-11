<?php declare(strict_types=1);

namespace Frosh\ThumbnailProcessorImgProxy\Service;

use Frosh\ThumbnailProcessor\Service\ThumbnailUrlTemplateInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ThumbnailUrlTemplate implements ThumbnailUrlTemplateInterface
{
    /** @var string */
    private string $domain;

    /** @var string */
    private string $key;

    /** @var string */
    private string $salt;

    /** @var string */
    private string $resizingType;

    /** @var string */
    private string $gravity;

    /** @var int */
    private int $signatureSize;

    /**
     * @var ThumbnailUrlTemplateInterface
     */
    private ThumbnailUrlTemplateInterface $parent;

    public function __construct(SystemConfigService $systemConfigService, ThumbnailUrlTemplateInterface $parent)
    {
        $this->domain = $systemConfigService->get('FroshPlatformThumbnailProcessorImgProxy.config.domain');
        $this->key = $systemConfigService->get('FroshPlatformThumbnailProcessorImgProxy.config.imgproxykey');
        $this->salt = $systemConfigService->get('FroshPlatformThumbnailProcessorImgProxy.config.imgproxysalt');
        $this->resizingType = $systemConfigService->get('FroshPlatformThumbnailProcessorImgProxy.config.resizingType') ?: 'fit';
        $this->gravity = $systemConfigService->get('FroshPlatformThumbnailProcessorImgProxy.config.gravity') ?: 'sm';
        $this->signatureSize = $systemConfigService->get('FroshPlatformThumbnailProcessorImgProxy.config.signatureSize') ?: 32;
        $this->parent = $parent;
    }

    /**
     * @param string $mediaUrl
     * @param string $mediaPath
     * @param string $width
     * @param string $height
     */
    public function getUrl(string $mediaUrl, string $mediaPath, string $width, ?\DateTimeInterface $mediaUpdatedAt): string
    {
        $keyBin = pack('H*', $this->key);
        $saltBin = pack('H*', $this->salt);

        if (empty($keyBin) || empty($saltBin)) {
            return $this->parent->getUrl($mediaUrl, $mediaPath, $width, $mediaUpdatedAt);
        }

        $extension = pathinfo($mediaPath, PATHINFO_EXTENSION);
        $encodedUrl = rtrim(strtr(base64_encode($mediaUrl . '/' . $mediaPath), '+/', '-_'), '=');

        $path = "/rs:{$this->resizingType}:{$width}/g:{$this->gravity}/{$encodedUrl}.{$extension}?t=" . $mediaUpdatedAt->getTimestamp();
        $signature = hash_hmac('sha256', $saltBin . $path, $keyBin, true);

        if ($this->signatureSize !== 32) {
            $signature = pack('A' . $this->signatureSize, $signature);
        }

        $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $this->domain . '/' . $signature . $path;
    }
}

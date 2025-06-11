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

    /** @var bool */
    private bool $omitExtension = false;

    /**
     * @var ThumbnailUrlTemplateInterface
     */
    private ThumbnailUrlTemplateInterface $parent;

    public function __construct(SystemConfigService $systemConfigService, ThumbnailUrlTemplateInterface $parent)
    {
        $this->domain = $systemConfigService->getString('FroshPlatformThumbnailProcessorImgProxy.config.domain');
        $this->key = $systemConfigService->getString('FroshPlatformThumbnailProcessorImgProxy.config.imgproxykey');
        $this->salt = $systemConfigService->getString('FroshPlatformThumbnailProcessorImgProxy.config.imgproxysalt');
        $this->resizingType = $systemConfigService->getString('FroshPlatformThumbnailProcessorImgProxy.config.resizingType') ?: 'fit';
        $this->gravity = $systemConfigService->getString('FroshPlatformThumbnailProcessorImgProxy.config.gravity') ?: 'sm';
        $this->signatureSize = $systemConfigService->getInt('FroshPlatformThumbnailProcessorImgProxy.config.signatureSize') ?: 32;
        $this->omitExtension = $systemConfigService->getBool('FroshPlatformThumbnailProcessorImgProxy.config.omitExtension') ?: false;
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

        $timestamp = $mediaUpdatedAt->getTimestamp();

        $path = "/rs:{$this->resizingType}:{$width}/g:{$this->gravity}/cb:{$timestamp}/{$encodedUrl}" . ($this->omitExtension ? '' : ".{$extension}");
        $signature = hash_hmac('sha256', $saltBin . $path, $keyBin, true);

        if ($this->signatureSize !== 32) {
            $signature = pack('A' . $this->signatureSize, $signature);
        }

        $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $this->domain . '/' . $signature . $path;
    }
}

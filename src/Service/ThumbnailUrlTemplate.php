<?php declare(strict_types=1);

namespace Nuonic\ThumbnailProcessorImgProxy\Service;

use Frosh\ThumbnailProcessor\Service\ThumbnailUrlTemplateInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ThumbnailUrlTemplate implements ThumbnailUrlTemplateInterface
{
    private string $domain;

    private string $key;

    private string $salt;

    private string $resizingType;

    private string $gravity;

    private int $signatureSize;

    private bool $omitExtension = false;

    private ThumbnailUrlTemplateInterface $parent;

    public function __construct(SystemConfigService $systemConfigService, ThumbnailUrlTemplateInterface $parent)
    {
        $this->domain = $systemConfigService->getString('NuonicPlatformThumbnailProcessorImgProxy.config.domain');
        $this->key = $systemConfigService->getString('NuonicPlatformThumbnailProcessorImgProxy.config.imgproxykey');
        $this->salt = $systemConfigService->getString('NuonicPlatformThumbnailProcessorImgProxy.config.imgproxysalt');
        $this->resizingType = $systemConfigService->getString('NuonicPlatformThumbnailProcessorImgProxy.config.resizingType') ?: 'fit';
        $this->gravity = $systemConfigService->getString('NuonicPlatformThumbnailProcessorImgProxy.config.gravity') ?: 'sm';
        $this->signatureSize = $systemConfigService->getInt('NuonicPlatformThumbnailProcessorImgProxy.config.signatureSize') ?: 32;
        $this->omitExtension = $systemConfigService->getBool('NuonicPlatformThumbnailProcessorImgProxy.config.omitExtension') || false;
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

        $timestamp = $mediaUpdatedAt?->getTimestamp() ?? 0;

        $path = "/rs:{$this->resizingType}:{$width}/g:{$this->gravity}/cb:{$timestamp}/{$encodedUrl}" . ($this->omitExtension ? '' : ".{$extension}");
        $signature = hash_hmac('sha256', $saltBin . $path, $keyBin, true);

        if ($this->signatureSize !== 32) {
            $signature = pack('A' . $this->signatureSize, $signature);
        }

        $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $this->domain . '/' . $signature . $path;
    }
}

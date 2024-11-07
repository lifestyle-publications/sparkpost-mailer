<?php declare(strict_types=1);

namespace Gam6itko\Symfony\Mailer\SparkPost\Mime;

use Symfony\Component\Mime\Email;

class SparkPostEmail extends Email
{
    use HasMetadataTrait;
    use HasSubstitutionDataTrait;

    private ?string $transmissionId = null;
    private ?string $campaignId = null;
    private ?string $description= null;
    private array $options = [];

    /**
     * @var array|null [from, subject, text, html, amp_html, reply_to, headers, attachments, inline_images]
     */
    private ?array $content = null;

    public function getTransmissionId(): ?string
    {
        return $this->transmissionId;
    }

    public function setTransmissionId(?string $transmissionId): SparkPostEmail
    {
        $this->transmissionId = $transmissionId;
        return $this;
    }

    public function ensureValidity(): void
    {
    }

    public function getCampaignId(): ?string
    {
        return $this->campaignId;
    }

    public function setCampaignId(?string $campaignId): SparkPostEmail
    {
        $this->campaignId = $campaignId;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): SparkPostEmail
    {
        $this->description = $description;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): SparkPostEmail
    {
        $this->options = $options;

        return $this;
    }

    public function getContent(): ?array
    {
        return $this->content;
    }

    public function setContent(?array $content): SparkPostEmail
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @internal
     */
    public function __serialize(): array
    {
        return [
            $this->campaignId,
            $this->description,
            $this->options,
            $this->content,
            $this->substitutionData,
            $this->metadata,
            parent::__serialize(),
        ];
    }

    /**
     * @internal
     */
    public function __unserialize(array $data): void
    {
        [
            $this->campaignId,
            $this->description,
            $this->options,
            $this->content,
            $this->substitutionData,
            $this->metadata,
            $parentData,
        ] = $data;

        parent::__unserialize($parentData);
    }
}

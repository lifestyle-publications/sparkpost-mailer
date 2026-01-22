<?php declare(strict_types=1);

namespace Gam6itko\Symfony\Mailer\SparkPost\Transport;

use Gam6itko\Symfony\Mailer\SparkPost\Mime\SparkPostEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SparkPostApiTransport extends AbstractApiTransport
{
    private string $key;
    private ?EventDispatcherInterface $dispatcher;

    public function __construct(string $key, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null, ?string $region = null, string $host = 'default')
    {
        $this->key = $key;

        if ('default' === $host) {
            $host = \sprintf('api%s.sparkpost.com', $region ? '.'.$region : '');
        }
        $this->host = $host;
        $this->dispatcher = $dispatcher;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('sparkpost+api://%s', $this->host);
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        // override to get rid of clone so that the message can be passed back via reference with the transmissionId
        $envelope = $envelope ?? Envelope::create($message);
        if (!$this->dispatcher) {
            $sentMessage = new SentMessage($message, $envelope);
            $this->doSend($sentMessage);

            return $sentMessage;
        }

        $event = new MessageEvent($message, $envelope, (string) $this);
        $this->dispatcher->dispatch($event);
        if ($event->isRejected()) {
            return null;
        }

        $envelope = $event->getEnvelope();
        $message = $event->getMessage();

        $sentMessage = new SentMessage($message, $envelope);

        try {
            $this->doSend($sentMessage);
        } catch (\Throwable $error) {
            $this->dispatcher->dispatch(new FailedMessageEvent($message, $error));

            throw $error;
        }

        $this->dispatcher->dispatch(new SentMessageEvent($sentMessage));

        return $sentMessage;
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $payload = [
            'recipients' => $this->buildRecipients($envelope),
            'content'    => $this->buildContent($email, $envelope),
        ];

        if ($email instanceof SparkPostEmail) {
            $payload = array_merge($payload, array_filter([
                'campaign_id'       => $email->getCampaignId(),
                'description'       => $email->getDescription(),
                'options'           => $email->getOptions(),
                'metadata'          => $email->getMetadata(),
                'substitution_data' => $email->getSubstitutionData(),
            ]));
        }

        $this->log($payload);

        $response = $this->client->request(
            'POST',
            \sprintf('https://%s/api/v1/transmissions/', $this->host),
            [
                'headers' => [
                    'Authorization' => $this->key,
                    'Content-Type'  => 'application/json',
                ],
                'json'    => $payload,
            ]
        );


        if ($response->getStatusCode() === 200) {
            if ($email instanceof SparkPostEmail) {
                $data = json_decode($response->getContent(false), true);
                $email->setTransmissionId($data['results']['id'] ?? null);
            }
        } else {
            $this->handleError($response);
        }

        return $response;
    }

    private function buildRecipients(Envelope $envelope): array
    {
        $result = [];
        foreach ($envelope->getRecipients() as $to) {
            $result[] = [
                'address' => array_filter([
                    'name'  => $to->getName(),
                    'email' => $to->getAddress(),
                ]),
            ];
        }

        return $result;
    }

    private function buildContent(Email $email, Envelope $envelope): array
    {
        //todo here we need to be able to specify our CCs and BCCs
        if ($email instanceof SparkPostEmail && $email->getContent()) {
            return $email->getContent();
        }

        $from = $envelope->getSender();

        return array_filter([
            'from'        => array_filter([
                'name'  => $from->getName(),
                'email' => $from->getAddress(),
            ]),
            'subject'     => $email->getSubject(),
            'headers'     => [
                'cc' => $email->getCc(),
            ],
            'text'        => $email->getTextBody(),
            'html'        => $email->getHtmlBody(),
            'replyTo'     => $email->getReplyTo(),
            'attachments' => $this->buildAttachments($email),
        ]);
    }

    private function buildAttachments(Email $email): array
    {
        $result = [];
        foreach ($email->getAttachments() as $attachment) {
            /** @var ParameterizedHeader $file */
            $file = $attachment->getPreparedHeaders()->get('Content-Disposition');
            /** @var ParameterizedHeader $type */
            $type = $attachment->getPreparedHeaders()->get('Content-Type');

            $result[] = [
                'name' => $file->getParameter('filename'),
                'type' => $type->getValue(),
                'data' => base64_encode($attachment->getBody()),
            ];
        }

        return $result;
    }

    private function log(array $payload): void
    {
        if (isset($payload['content']['attachments']) && is_array($payload['content']['attachments'])) {
            foreach ($payload['content']['attachments'] as &$attachment) {
                if (isset($attachment['data']) && strlen($attachment['data']) > 100) {
                    $attachment['data'] = '<<<truncated>>>';
                }
            }
        }
        $this->getLogger()->debug('SparkPostApiTransport send', $payload);
    }

    private function handleError(ResponseInterface $response): void
    {
        $data = json_decode($response->getContent(false), true);
        $this->getLogger()->error('SparkPostApiTransport error response', $data);

        throw new HttpTransportException(json_encode($data['errors']), $response, $response->getStatusCode());
    }
}

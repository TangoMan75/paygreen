<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Repository\OperationRepository;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class UserEncoder implements EncoderInterface, DecoderInterface
{
    const FORMAT = 'user:json';

    private $encoder;

    private $operationRepository;

    public function __construct(OperationRepository $operationRepository)
    {
        $this->operationRepository = $operationRepository;

        // JsonEncoder cannot autowire as a service
        $this->encoder = new JsonEncoder();
    }

    public function encode($data, $format, array $context = [])
    {
        // encode data as json
        return $this->encoder->encode(
            array_map(
                [
                    $this,
                    'encodeItem',
                ],
                $data
            ),
            'json'
        );
    }

    public function supportsEncoding($format): bool
    {
        return self::FORMAT === $format;
    }

    public function decode($data, $format, array $context = [])
    {
        // decode data as array with json decoder
        $data = $this->encoder->decode($data, 'json');

        // create relations
        $operations = [];
        foreach ($data['operations'] ?? [] as $operation) {
            // get id from IRI string
            $iri = explode('/', $operation);
            // request each object from database
            $operations[] = $this->operationRepository->find(\intval(end($iri)));
        }
        $data['operations'] = $operations;

        return $data;
    }

    public function supportsDecoding($format): bool
    {
        return self::FORMAT === $format;
    }

    public function encodeItem(array $item): array
    {
        // encode relations as IRI
        $operations = [];
        foreach ($item['operations'] as $operation) {
            $operations[] = sprintf('/operations/%s', $operation['id']);
        }
        $item['operations'] = $operations;

        return $item;
    }
}

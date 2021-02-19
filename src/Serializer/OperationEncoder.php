<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Repository\UserRepository;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class OperationEncoder implements EncoderInterface, DecoderInterface
{
    const FORMAT = 'operation:json';

    private $encoder;

    private $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;

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

        // convert timestamp to datetime
        $date = new \DateTime();
        $data['dateCreated'] = $date->setTimestamp($data['dateCreated']);

        // create relation
        $iri = explode('/', $data['owner']);
        $data['owner'] = $this->userRepository->find(\intval(end($iri)));

        return $data;
    }

    public function supportsDecoding($format): bool
    {
        return self::FORMAT === $format;
    }

    public function encodeItem(array $item): array
    {
        // transform dateTime to timestamp
        $item['dateCreated'] = $item['dateCreated']['timestamp'] ?? null;

        // encode relation as IRI
        $item['owner'] = sprintf('/user/%s', $item['owner']['id']);

        return $item;
    }
}

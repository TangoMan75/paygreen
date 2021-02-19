<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\OperationRepository;
use App\Serializer\UserEncoder;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class SecurityController extends AbstractController
{
    private $serializer;

    private $passwordEncoder;

    public function __construct(
        OperationRepository $operationRepository,
        UserPasswordEncoderInterface $passwordEncoder
    ) {
        $encoder = new UserEncoder($operationRepository);
        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) {
                return $object->getId();
            },
        ];
        $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
        $this->serializer = new Serializer([$normalizer], [$encoder]);

        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @Route("/register", methods={"POST"})
     */
    public function register(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->serializer->deserialize($request->getContent(), User::class, 'user:json');

        $user->setRoles(['ROLE_USER']);

        // encode password
        $user->setPassword(
            $this->passwordEncoder->encodePassword(
                $user,
                $user->getPassword()
            )
        );

        $entityManager->persist($user);
        $entityManager->flush();

        $json = $this->serializer->serialize([$user], 'user:json');

        return new JsonResponse($json, Response::HTTP_CREATED, [], true);
    }

    /**
     * @Route("/login", methods={"POST"})
     */
    public function login(Request $request): JsonResponse
    {
        $user = $this->getUser();

        return $this->json(
            [
                'username' => $user->getUsername(),
                'roles'    => $user->getRoles(),
            ]
        );
    }

    /**
     * @Route("/logout", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @throws \Exception
     */
    public function logout()
    {
        // controller can be blank: it will never be executed!
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
    }
}

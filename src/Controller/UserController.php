<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\OperationRepository;
use App\Repository\UserRepository;
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

class UserController extends AbstractController
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
     * @Route("/users", methods={"GET"})
     * @IsGranted("ROLE_ADMIN", statusCode=401, message="Unauthorized")
     */
    public function list(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        $json = $this->serializer->serialize($users, 'user:json');

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    /**
     * Only Admins have permission to create users.
     *
     * @Route("/users", methods={"POST"})
     * @IsGranted("ROLE_ADMIN", statusCode=401, message="Unauthorized")
     */
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->serializer->deserialize($request->getContent(), User::class, 'user:json');

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
     * @Route("/users/{id}", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function read(User $user): Response
    {
        $json = $this->serializer->serialize([$user], 'user:json');

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    /**
     * @Route("/users/{id}", methods={"DELETE"}, requirements={"id": "\d+"})
     */
    public function delete(User $user, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}

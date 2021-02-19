<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Operation;
use App\Entity\User;
use App\Repository\OperationRepository;
use App\Repository\UserRepository;
use App\Serializer\OperationEncoder;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class OperationController extends AbstractController
{
    private $serializer;

    public function __construct(UserRepository $userRepository)
    {
        $encoder = new OperationEncoder($userRepository);
        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) {
                return $object->getId();
            },
        ];
        $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
        $this->serializer = new Serializer([$normalizer], [$encoder]);
    }

    /**
     * @Route("/operations", methods={"GET"})
     */
    public function list(OperationRepository $operationRepository): Response
    {
        $operations = $operationRepository->findAll();
        $json = $this->serializer->serialize($operations, 'operation:json');

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    /**
     * @Route("/operations", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY", statusCode=401, message="Unauthorized")
     */
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $operation = $this->serializer->deserialize($request->getContent(), Operation::class, 'operation:json');

        // Only logged in user can create operation
        /** @var User $user */
        $user = $this->getUser();
        $operation->setOwner($user);
        $user->addOperation($operation);

        $entityManager->persist($user);
        $entityManager->persist($operation);
        $entityManager->flush();

        $json = $this->serializer->serialize([$operation], 'operation:json');

        return new JsonResponse($json, Response::HTTP_CREATED, [], true);
    }

    /**
     * @Route("/operations/{id}", methods={"GET"}, requirements={"id": "\d+"})
     */
    public function read(Operation $operation): Response
    {
        $json = $this->serializer->serialize([$operation], 'operation:json');

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    /**
     * @Route("/operations/{id}", methods={"DELETE"}, requirements={"id": "\d+"})
     * @IsGranted("IS_AUTHENTICATED_FULLY", statusCode=401, message="Unauthorized")
     */
    public function delete(Operation $operation, EntityManagerInterface $entityManager): Response
    {
        // Only owner or admin can edit post
        if ($this->getUser() !== $operation->getOwner()
            && !$this->get('security.authorization_checker')->isGranted(
                'ROLE_ADMIN'
            )
        ) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($operation);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}

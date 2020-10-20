<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Repository\ParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/messages", name="messages.")
 */
class MessageController extends AbstractController
{
    public const ATTRIBUTES_TO_SERIALIZE = ['id', 'content', 'createdAt', 'mine'];
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var MessageRepository
     */
    private $messageRepository;
    /**
     * @var ParticipantRepository
     */
    private $participantRepository;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var PublisherInterface
     */
    private $publisher;

    public function __construct(
        EntityManagerInterface $entityManager,
        MessageRepository $messageRepository,
        ParticipantRepository $participantRepository,
        SerializerInterface $serializer,
        PublisherInterface $publisher
    )
    {
        $this->entityManager = $entityManager;
        $this->messageRepository = $messageRepository;
        $this->participantRepository = $participantRepository;
        $this->serializer = $serializer;
        $this->publisher = $publisher;
    }

    /**
     * @Route("/{id}", name="getMessages", methods={"GET"})
     * @param Request $request
     * @param Conversation $conversation
     * @return Response
     */
    public function index(Request $request, Conversation $conversation)
    {
        $this->denyAccessUnlessGranted('view', $conversation);

        $messages = $this->messageRepository->findMessageByConversationId($conversation->getId());

        /**
         * @var $message Message
         */
        array_map(function ($message){
            $message->setMine(
                $message->getUser()->getId() === $this->getUser()->getId()
            );
        },$messages);

        return $this->json($messages, Response::HTTP_OK, [], [
            'attributes' => self::ATTRIBUTES_TO_SERIALIZE
        ]);
    }

    /**
     * @Route("/{id}", name="newMessage", methods={"POST"})
     * @param Request $request
     * @param Conversation $conversation
     * @return JsonResponse
     * @throws Exception
     */
    public function newMessage(Request $request, Conversation $conversation)
    {
        $user = $this->getUser();

        $recipient =
            $this->participantRepository->
            findParticipantByConversationIdAndUserId($conversation->getId(), $user->getId());

        $content = $request->get('content', null);

        $message = new Message();
        $message->setContent($content);
        $message->setUser($user);

        $conversation->addMessage($message);
        $conversation->setLastMessage($message);

        $this->entityManager->getConnection()->beginTransaction();
        try {
            $this->entityManager->persist($message);
            $this->entityManager->persist($conversation);
            $this->entityManager->flush();

            $this->entityManager->commit();
        } catch (Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        $message->setMine(false);

        $messageSerialized = $this->serializer->serialize($message, 'json', [
            'attributes' => ['id', 'content', 'createdAt', 'mine', 'conversation' => ['id']]
        ]);

        $update = new Update(
            [
                sprintf("/conversations/%s", $conversation->getId()),
                sprintf("/conversations/%s", $recipient->getUser()->getUsername()),
            ],
            $messageSerialized,
            true
        );

        $this->publisher->__invoke($update);

        $message->setMine(true);
        return $this->json($message, Response::HTTP_CREATED, [], [
            'attributes' => self::ATTRIBUTES_TO_SERIALIZE
        ]);
    }
}

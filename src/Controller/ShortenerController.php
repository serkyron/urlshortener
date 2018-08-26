<?php

namespace App\Controller;

use App\Entity\UrlPair;
use App\Validator\Constraints\UnusedShortUrl;
use App\Validator\Constraints\ValidUrl;
use Doctrine\ORM\EntityManager;
use PharIo\Manifest\Url;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShortenerController extends AbstractController
{
    /**
     * Corresponds to application home page
     *
     * @Route("/", name="home")
     * @Method({"GET"})
     */
    public function index()
    {
        return $this->render('home.html.twig');
    }

    /**
     * @Route("/api/shorten/url", name="shorten")
     * @Method({"POST"})
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    public function shorten(Request $request, SerializerInterface $serializer, ValidatorInterface $validator)
    {
        $violations = $this->validateShortenRequest($request, $validator);

        if (count($violations))
        {
            $errors = $serializer->serialize($violations, 'json');
            return JsonResponse::fromJsonString($errors, 400);
        }

        $entityManager = $this->getDoctrine()->getManager();

        //create a new url pair
        $urlPair = new UrlPair();
        $urlPair->setLongUrl($request->get('long_url'));
        $urlPair->setCreatedAt(new \DateTime());

        if ($request->query->has('requested'))
            $urlPair->setShortUrl($request->get('requested'));
        else
            $urlPair->setShortUrl($this->getUniqueShortUrl($entityManager));

        //save the new url pair
        $entityManager->persist($urlPair);
        $entityManager->flush();

        return new JsonResponse([
            'short_url' => $urlPair->getShortUrl()
        ]);
    }

    private function getUniqueShortUrl(EntityManager $entityManager)
    {
        $rep = $entityManager->getRepository(UrlPair::class);
        $codeLength = 16;

        do {
            $code = bin2hex(random_bytes($codeLength));
            $pair = $rep->findByShortUrl($code);
        }
        while($pair);

        return $code;
    }

    private function validateShortenRequest(Request $request, ValidatorInterface $validator)
    {
        $constraints = [
            'long_url' => [
                new NotBlank(),
                new ValidUrl()
            ]
        ];

        if ($request->query->has('requested'))
        {
            $constraints['requested'] = [
                new Length([
                    'min' => 5,
                    'max' => 20
                ]),
                new UnusedShortUrl()
            ];
        }

        return $validator->validate($request->query->all(), new Collection($constraints));
    }
}
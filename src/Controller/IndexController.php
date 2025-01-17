<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contact;
use App\FormType\ContactType;
use App\Service\EmailService;
use App\Service\HashService;
use App\Service\QueryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    /**
     * Path where to store the uploaded cv
     */
    public const CV_ASSET_DIR = '../uploads/assets';
    /**
     * Filename of the uploaded cv
     */
    public const CV_ASSET_FILENAME = 'Steckbrief.pdf';
    /**
     * The email address where the emails will send to from the contact form
     */
    protected $reciverEmailAddress;

    /**
     * EmailService used to send emails
     *
     * @var EmailService $emailService
     */
    protected EmailService $emailService;

    /**
     * HashService to generate and validate hashes
     * @var HashService $hashService
     */
    protected HashService $hashService;

    /**
     * IndexController constructor.
     * @param EmailService $emailService
     * @param HashService $hashService
     */
    public function __construct(EmailService $emailService, HashService $hashService, $reciverEmailAddress)
    {
        $this->emailService = $emailService;
        $this->hashService = $hashService;
        $this->reciverEmailAddress = $reciverEmailAddress;
    }

    /**
     * @Route("/", name="home")
     */
    public function index(Request $request, QueryService $queryService, Filesystem $filesystem): Response
    {
        // set some default variables
        /*
         * Erstelle eine Variable mit der Bezeichnung 'showDownloadButton' und weise ihr den Wert false zu
         */
        $showDownloadButton = false;

        // create new empty contact object
        /*
         * In diesem Projekt gibt es ein Klasse, die sich Contact nennt. Diese hält die Informationen aus dem
         * Kontaktformular so fern welche eingegeben wurden.
         *
         * Erstelle eine neue Variabel die 'contact' heißt und weise ihr ein neu erstelltes Contact Objekt zu.
         */
        $contact = new Contact();

        // create new Form for ContactType (the ContactType defines the form fields)
        /*
         * Damit das Formular angezeigt wird, müssen wir dieses erstellen. Dazu hat diese Klasse eine Funktion, die
         * heißt 'createForm'. Diese Funktion besitzt zwei Parameter. Einmal die Definition des Formulars und ein
         * leeres Objekt, indem die eingegebene Daten gespeichert werden.
         *
         * Erstelle eine Variable mit dem namen 'form'. Erstelle mit der oben genannten Funktion ein neues Formular und
         * weise es der Variable zu.
         */
        $form = $this->createForm(ContactType::class, $contact);
        
        // load form input from request if they are available
        $form->handleRequest($request);
        // check if form is submitted and if all values are syntactically correct
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // send email with contact infos
                /*
                 * Sende eine Email mit dem in dieser Klasse zur Verfügung gestellten Email Service.
                 *
                 * Schau dir dazu noch einmal an welche Informationen in der Contact Klasse zu finden ist.
                 *
                 * Weitere Informationen:
                 * - Der Templatename: emails/contact.html.twig
                 * - Das Template benötigt folgende Informationen aus der Contact Entität:
                 *  - 'name' -> Der Name
                 *  - 'from' --> Die Email des Ausfüllers des Kontaktformulares
                 *  - 'message' --> Die Nachricht
                 *
                 */ 
                $this->emailService->sendMail(
                    $contact->getEmail(),
                    $this->reciverEmailAddress,
                    $contact->getSubject(),
                    "emails/contact.html.twig",
                    [
                        "message" => $contact->getMessage(),
                        "from" => $contact->getEmail(),
                        "name" => $contact->getName()
                    ]
                );

                // add flash message to notice the user the success message sending
                $this->addFlash('contact-success', 'Ihre Nachricht wurde erfolgreich gesendet!');
            } catch (\Exception $e) {
                // add flash message to notice the user that something went wrong
                $this->addFlash('contact-danger', 'Ihre Nachricht kann derzeit nicht zugestellt werden!');

                // render template
                return $this->renderForm('landingpage/index.html.twig', [
                    'showDownloadButton' => $showDownloadButton,
                    'form' => $form,
                ]);
            }

            // redirect to home route
            return $this->redirectToRoute('home');
        }

        $showDownloadButton = $this->hashService->validateHash($request);

        // render template
        /*
         * Als Letztes müssen, wir noch das Formular rendern also dass es generiert wird. Dies wird mit der
         * Funktion 'renderForm', welche ebenfalls in dieser Klasse ist, gemacht. Die Funktion benötigt zwei Parameter:
         * - Der Name des Templates, welches zum rendern verwendet werden soll: landingpage/index.html.twig
         * - Der Context in Form eines Arrays, welcher die Daten zum rendern enthält
         *
         * Der Context muss folgende Daten enthalten:
         * - das 'form' welches wir oben erstellt haben
         * - die Variable 'showDownloadButton'
         * - und der Hash welchen du mithilfe folgendem Aufruf bekommst:
         *      - "$this->hashService->getHash($request)"
         *
         * Den Rückgabewert der Funktion werden wir dieses mal nicht in eine Variable speichern, sondern werden ihn
         * direkt mit dem Schlüsselwort 'return' zurückgeben.
         *
         */return $this->renderForm('landingpage/index.html.twig',
            [ "form" => $form,
                "showDownloadButton" => $showDownloadButton,
                $this->hashService->getHash($request)
            ]
         );
    }

    /**
     * @Route("/downloadCv", name="downloadCv")
     */
    public function getUploadedCv(Filesystem $filesystem, Request $request): Response
    {
        if (!$this->hashService->validateHash($request)) {
            return new Response('Not authorized', Response::HTTP_FORBIDDEN);
        }

        $filename = self::CV_ASSET_DIR.\DIRECTORY_SEPARATOR.self::CV_ASSET_FILENAME;
        if (!$filesystem->exists($filename)) {
            return new Response('File not set yet!', Response::HTTP_NOT_FOUND);
        }
        return new BinaryFileResponse($filename);
    }
}

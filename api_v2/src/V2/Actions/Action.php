<?php
declare(strict_types=1);

namespace Api\V2\Actions;

use Api\V2\DomainException\DomainRecordNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

abstract class Action
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var array
     */
    protected $args;


    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger )
    {
        $this->logger = $logger;
    }

    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     * @return Response
     * @throws HttpNotFoundException
     * @throws HttpBadRequestException
     */
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;

        try {
            return $this->action();
        } catch (DomainRecordNotFoundException $e) {
            throw new HttpNotFoundException($this->request, $e->getMessage());
        }
    }

    /**
     * @return Response
     * @throws DomainRecordNotFoundException
     * @throws HttpBadRequestException
     */
    abstract protected function action(): Response;

    /**
     * @return array|object
     * @throws HttpBadRequestException
     */
    protected function getFormData()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpBadRequestException($this->request, 'Malformed JSON input.');
        }

        return $input;
    }

    /**
     * @param  string $name
     * @return mixed
     * @throws HttpBadRequestException
     */
    protected function resolveArg(string $name)
    {
        if (!isset($this->args[$name])) {
            throw new HttpBadRequestException($this->request, "Could not resolve argument `{$name}`.");
        }

        return $this->args[$name];
    }

    /**
     * @param  array|object|null $data
     * @return Response
     */
    protected function respondWithData($data = null, $meta = null): Response
    {
        $payload = new ActionPayload(200, $data, $meta);
        return $this->respond($payload);
    }

    /**
     * @param ActionPayload $payload
     * @return Response
     */
    protected function respond(ActionPayload $payload): Response
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT   );
        $this->response->getBody()->write($json);
        return $this->response->withHeader('Content-Type', 'application/json');
    }

    protected function replaceQueryParams(&$form, $strict = true) {
        $query = $this->request->getQueryParams();
        if($strict) {
            foreach ($form as $key=>$_) {
                if(isset($query[$key])) {
                    $form[$key] = $query[$key];
                }
            }
        } else {
            foreach ($query as $key => $value) {
                $form[$key] =  is_string($value) ? trim($value) : $value;
            }
        }
        return $this;
    }
    protected function fillDefaultKeys($defaultKeys, &$form) {
        foreach ($defaultKeys as $key => $val) {
            if(!isset($form[$key])) {
                $form[$key] = $val;
            }
        }
        return $this;
    }


}

<?php

namespace Drupal\tmgmt_aac\Plugin\tmgmt\Translator;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\tmgmt\ContinuousTranslatorInterface;
use Drupal\tmgmt\Entity\RemoteMapping;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\SourcePreviewInterface;
use Drupal\tmgmt\TMGMTException;
use Drupal\tmgmt\Translator\AvailableResult;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;
use Drupal\tmgmt_file\Format\FormatManager;
use Drupal\tmgmt_file\Plugin\tmgmt_file\Format\Xliff;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AAC Global translation plugin controller.
 *
 * @TranslatorPlugin(
 *   id = "aac",
 *   label = @Translation("AAC Global"),
 *   description = @Translation("AAC Global translator service."),
 *   logo = "icons/aac.svg",
 *   ui = "Drupal\tmgmt_aac\AacTranslatorUi",
 * )
 */
class AacTranslator extends TranslatorPluginBase implements ContainerFactoryPluginInterface, ContinuousTranslatorInterface {
  use StringTranslationTrait;

  /**
   * Translation service URL.
   */
  const SERVICE_URL = 'https://62.237.83.60';

  /**
   * Guzzle HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The format manager.
   *
   * @var \Drupal\tmgmt_file\Format\FormatManager
   */
  protected $formatManager;

  /**
   * Constructs an AAC Translator object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The Guzzle HTTP client.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\tmgmt_file\Format\FormatManager $format_manager
   *   The TMGMT file format manager.
   */
  public function __construct(ClientInterface $client, array $configuration, $plugin_id, array $plugin_definition, FormatManager $format_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
    $this->formatManager = $format_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
//    /** @var \GuzzleHttp\ClientInterface $client */
//    $client = $container->get('http_client');
    // @TODO put back the default http_http client. This is a temporary workaround for self-signed SSL certificate issues
    $clientFactory = $container->get('http_client_factory');
    $client = $clientFactory->fromOptions([
      'verify' => FALSE,
    ]);
    /** @var \Drupal\tmgmt_file\Format\FormatManager $format_manager */
    $format_manager = $container->get('plugin.manager.tmgmt_file.format');
    return new static(
      $client,
      $configuration,
      $plugin_id,
      $plugin_definition,
      $format_manager
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkAvailable(TranslatorInterface $translator) {
    if ($translator->getSetting('username') && $translator->getSetting('password')) {
      return AvailableResult::yes();
    }
    return AvailableResult::no($this->t('@translator is not available. Make sure it is properly <a href=:configured>configured</a>.', [
      '@translator' => $translator->label(),
      ':configured' => $translator->toUrl()->toString(),
    ]));
  }

  /**
   * Gets the account info.
   */
  public function getAccount(TranslatorInterface $translator) {
    try {
      return $this->doRequest($translator, 'me');
    }
    catch (TMGMTException $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslation(JobInterface $job) {
    $this->requestJobItemsTranslation($job->getItems());
    if (!$job->isRejected()) {
      $job->submitted('Job has been successfully submitted for translation.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function requestJobItemsTranslation(array $job_items) {

  }

  /**
   * Executes a request against AAC Global API.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   * @param string $path
   *   Resource path.
   * @param array $parameters
   *   (optional) Parameters to send to AAC Global service.
   * @param string $method
   *   (optional) HTTP method (GET, POST...). Defaults to GET.
   *
   * @return array|string
   *   Response array from AAC Global.
   *
   * @throws \Drupal\tmgmt\TMGMTException
   */
  protected function doRequest(TranslatorInterface $translator, $path, array $parameters = [], $method = 'GET') {
    $url = self::SERVICE_URL . '/api/' . $path;
    $credentials = base64_encode($translator->getSetting('username') . ':' . $translator->getSetting('password'));

    try {
      // Add the authorization header.
      $options['headers']['Authorization'] = 'Basic ' . $credentials;
      if ($method == 'GET') {
        $options['query'] = $parameters;
      }
      else {
        $options += $parameters;
      }
      // Make a request.
      $response = $this->client->request($method, $url, $options);
    }
    catch (BadResponseException $e) {
      $response = $e->getResponse();
      throw new TMGMTException('Unable to connect to AAC Global service due to following error: @error', ['@error' => $response->getReasonPhrase()], $response->getStatusCode());
    }

    if ($response->getStatusCode() != 200) {
      throw new TMGMTException('Unable to connect to the AAC Global service due to following error: @error at @url',
        ['@error' => $response->getStatusCode(), '@url' => $url]);
    }

    $body = $response->getBody()->getContents();
    $received_data = json_decode($body, TRUE);
    // In case JSON decoding fails, return the plain body.
    if (!$received_data) {
      return $body;
    }

    return $received_data;
  }

}

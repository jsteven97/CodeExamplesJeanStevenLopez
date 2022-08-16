<?php

/**
 * Hello, I'm just gonna paste some code examples of what I did in some
 * functions or classes, you can see some context in the comment of each
 * code example.
 */

/**
 * This class is a proccessor to make a assign a migrate taxonomy terms in D7 to
 * a existing taxonomy terms in D9, I did this because the taxonomy in D9 has
 * new fields and I need to keep this fields values after migrate.
 */
class SourceMap extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  const SOURCE_MAP = [
    'Estadísticas demográficas' => 'Estadísticas vitales',
    'INEC' => 'INEC',
    'Estadísticas vitales' => 'Estadísticas vitales',
    'Censos' => 'Censos',
    'Censo cafetalero' => 'Censo cafetalero',
    'Censo 2022' => 'Censo 2022',
    'Censo 2011' => 'Censo 2011',
    'Censo 2000' => 'Censo 2000',
    'Censo Agropecuario 2014' => 'Censo Agropecuario 2014',
    'Economía' => 'Estadísticas económicas',
    'Canasta básica alimentaria' => 'Canasta básica alimentaria',
    'Comercio Exterior' => 'Comercio Exterior',
    'Directorio de empresas y establecimientos' => 'Directorio de empresas y establecimientos',
    'Índice de precios de la construcción' => 'Índice de precios de la construcción',
    'Índice de precios al consumidor' => 'Índice de precios al consumidor',
    'Estadísticas de construcción' => 'Estadísticas de construcción',
    'Encuestas' => 'Encuestas',
    'Encuesta de Mujeres, Niñez y Adolescencia 2018' => 'Encuesta de Mujeres, Niñez y Adolescencia 2018',
    'Encuesta Continua de Empleo' => 'Encuesta Continua de Empleo',
    'Encuesta Nacional sobre Discapacidad' => 'Encuesta Nacional sobre Discapacidad',
    'Encuesta Nacional Agropecuaria' => 'Encuesta Nacional Agropecuaria',
    'Encuesta Nacional de Microempresas de los Hogares' => 'Encuesta Nacional de Microempresas de los Hogares',
    'Encuesta Nacional de Cultura' => 'Encuesta Nacional de Cultura',
    'Encuesta de Hogares de Propósitos Múltiples' => 'Encuesta de Hogares de Propósitos Múltiples',
    'Encuesta Nacional a Empresas' => 'Encuesta Nacional a Empresas',
    'Encuesta Nacional de Hogares' => 'Encuesta Nacional de Hogares',
    'Encuesta Nacional de Hogares Productores' => 'Encuesta Nacional de Hogares Productores',
    'Encuesta Nacional de Ingresos y Gastos de los Hogares' => 'Encuesta Nacional de Ingresos y Gastos de los Hogares',
    'Encuesta Nacional de Puestos de Trabajo' => 'Encuesta Nacional de Puestos de Trabajo',
    'Encuesta Nacional de Uso del Tiempo' => 'Encuesta Nacional de Uso del Tiempo',
  ];

  /**
   * The termStorage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * Class constructor.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
  }

  /**
   * Create the Source Map Plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   * @param array $configuration
   *   The configuration data.
   * @param string $plugin_id
   *   The Plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Set the connection with D7 migration database.
    Database::setActiveConnection('migrate');

    // Get a connection with the D7 migration database.
    $db = Database::getConnection();

    // Make a query to get the source name of D7.
    $query = $db->select('taxonomy_term_data', 'td');
    $query->fields('td', ['name'])
      ->condition('vid', 19)
      ->condition('tid', $value);
    $term = $query->execute()->fetchField();

    // Switch back.
    Database::setActiveConnection();

    // Map the items of the constant to the new terms in D7.
    foreach (SourceMap::SOURCE_MAP as $key => $name) {
      if ($term == $key) {
        $results = $this->termStorage->loadByProperties([
          'vid' => 'sources',
          'name' => $name,
        ]);
        $results = array_values($results);
        if (isset($results[0])) {
          return $results[0]->id();
        }
      }
    }
  }

}

/**
 * This class is a service to convert a csv file to a json file, I did this
 * because the frontend team need this information in a json to contruct a
 * interactive map in the website.
 */
class CsvProcessor {

  use StringTranslationTrait;

  /**
   * Constructs a CsvProcessor service.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Convert a csv to json for inec maps.
   *
   * @param string $fileUri
   *   The file Uri.
   */
  public function csvToJson(string $fileUri) {
    $csvHandler = fopen($fileUri, "r");
    $index = 0;
    $data = new \StdClass();
    $data->labels = [];
    $data->data = [];
    while ($row = fgetcsv($csvHandler, 0, ",")) {
      // This condition is neccesary because the first row of the csv is use
      // to complete only labels information.
      if ($index === 0) {
        foreach ($row as $key => $item) {
          if ($key > 1) {
            $data->labels[] = $item;
          }
        }
      }
      else {
        $locationData = new \StdClass();
        $locationData->province = $row[0];
        $locationData->canton = $row[1];
        $locationData->values = [];
        foreach ($row as $key => $item) {
          if ($key > 1) {
            $locationData->values[] = $item;
          }
        }
        $data->data[] = $locationData;
      }
      $index++;
    }
    return json_encode($data);
  }

  /**
   * Validate the csv when is submit in the form.
   *
   * @param string $fileUri
   *   The file Uri.
   */
  public function csvValidation(string $fileUri) {
    if (mb_detect_encoding(file_get_contents($fileUri), NULL, TRUE) != 'UTF-8') {
      return $this->t('This file should be encoded in UTF-8');
    }
    $csvHandler = fopen($fileUri, "r");
    $index = 0;
    while ($row = fgetcsv($csvHandler, 0, ",")) {
      // Ignore the first line of the csv.
      if ($index != 0) {
        foreach ($row as $item) {
          // Thie regex is for accept only decimals with only one point(.).
          if (!preg_match("/^\d*\.?\d*$/", $item)) {
            return $this->t('The CSV data does not match the required format. Only numbers are accepted for value columns, Valid number example: 10.30.');
          }
        }
      }
      $index++;
    }
  }

}

/**
 * Implements hook_tokens().
 * I implement this hook to expose a new variable in the drupal token, this
 * token is used in a webform handler and this the easier way what I found to
 * get this variable to all backend site.
 */
function inec_forms_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];
  $data = \Drupal::config('manati_custom_ui.frontend_site.settings')->get('site_url');
  foreach ($tokens as $name => $original) {
    switch ($name) {
      case 'frontend_url_site':
        $replacements[$original] = $data;
        break;
    }
  }
  return $replacements;
}
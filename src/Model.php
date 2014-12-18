<?php
/**
 * Part of the ETD Framework Model Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Model;

use EtdSolutions\Application\Web;
use EtdSolutions\Table\Table;
use Joomla\Model\AbstractDatabaseModel;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/**
 * Modèle de base
 */
abstract class Model extends AbstractDatabaseModel {

    /**
     * Indique si l'état interne du modèle est définit
     *
     * @var    boolean
     */
    protected $__state_set = null;

    /**
     * @var  Model  L'instance de l'application.
     */
    private static $instance;

    protected $name;

    /**
     * @var array Un tableau des erreurs.
     */
    protected $errors = array();

    /**
     * Instancie le modèle.
     *
     * @param Registry $state          L'état du modèle.
     * @param bool     $ignore_request Utilisé pour ignorer la mise à jour de l'état depuis la requête.
     */
    public function __construct(Registry $state = null, $ignore_request = false) {

        if ($ignore_request) {
            $this->__state_set = true;
        }

        parent::__construct(Web::getInstance()
                               ->getDb(), $state);
    }

    /**
     * Méthode pour récupérer une instance d'un modèle, la créant si besoin.
     *
     * @param   string $name            Le nom du modèle
     * @param   bool   $ignore_request  Utilisé pour ignorer la mise à jour de l'état depuis l'input.
     *
     * @return  Model  L'instance.
     *
     * @throws   \RuntimeException
     */
    public static function getInstance($name, $ignore_request = false) {

        $name = ucfirst($name);
        $store = md5($name.":".(int)$ignore_request);

        if (empty(self::$instance[$store])) {

            // On définit la liste des espaces de noms dans laquelle le modèle peut se trouver.
            $namespaces = array(
                Web::getInstance()->get('app_namespace'),
                '\\EtdSolutions\\Framework'
            );

            $className = "";

            // On cherche le modèle dans ces espaces de nom.
            foreach ($namespaces as $namespace) {

                $className = $namespace . '\\Model\\' . $name . 'Model';

                // Si on a trouvé la classe, on arrête.
                if (class_exists($className)) {
                    break;
                }

            }
            // On vérifie que l'on a bien une classe valide.
            if (!class_exists($className)) {
                throw new \RuntimeException("Unable find model " . $name, 500);
            }

            self::$instance[$store] = new $className(null, $ignore_request);
        }


        return self::$instance[$store];
    }

    /**
     * Méthode pour récupérer un objet Table, en le chargeant si nécessaire.
     *
     * @param   string $name    Le nom du Table. Optionnel.
     *
     * @return  Table  Un objet Table
     *
     * @throws  \RuntimeException
     *
     * @note    Proxy vers Table::getInstance().
     */
    public function getTable($name = null) {

        if (!isset($name)) {
            $name = $this->getName();
        }

        return Table::getInstance($name);
    }

    /**
     * Récupère une valeur dans l'état du modèle.
     *
     * @param   string $path    Chemin dans le registre
     * @param   mixed  $default Une valeur par défaut optionnelle.
     *
     * @return  mixed   La valeur ou null
     */
    public function get($path, $default = null) {

        if (!$this->__state_set) {

            // Méthode pour remplir automatiquement l'état du modèle.
            $this->populateState();

            // On dit que l'état est définit.
            $this->__state_set = true;
        }

        return $this->state->get($path, $default);
    }

    /**
     * Définit une valeur par défaut dans l'état du modèle.
     * Si une valeur est déjà présente, on ne la change pas.
     *
     * @param   string $path    Chemin dans le registre
     * @param   mixed  $default Une valeur par défaut optionnelle.
     *
     * @return  mixed   La valeur ou null
     */
    public function def($path, $default = null) {

        return $this->state->def($path, $default);
    }

    /**
     * Définit une valeur dans l'état du modèle.
     *
     * @param   string $path  Chemin dans le registre
     * @param   mixed  $value Une valeur par défaut optionnelle.
     *
     * @return  mixed  La valeur précédente si elle existe.
     */
    public function set($path, $value) {

        return $this->state->set($path, $value);
    }

    /**
     * Méthode pour récupérer le nom du modèle.
     *
     * @return  string  Le nom du modèle.
     *
     * @throws  \RuntimeException
     */
    public function getName() {

        if (empty($this->name)) {
            $r         = null;
            $classname = join('', array_slice(explode('\\', get_class($this)), -1));
            if (!preg_match('/(.*)Model/i', $classname, $r)) {
                throw new \RuntimeException('Unable to detect model name', 500);
            }
            $this->name = $r[1];
        }

        return $this->name;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getError() {
        return count($this->errors) ? $this->errors[0] : false;
    }

    public function setErrors($errors) {
        $this->errors = $errors;
    }

    public function setError($error) {
        $this->errors[] = $error;
    }

    /**
     * Méthode pour définir automatiquement l'état du modèle.
     *
     * Cette méthode doit être appelée une fois par instanciation et est
     * conçue pour être appelée lors du premier appel de get() sauf si le
     * la configuration du modèle dit de ne pas l'appeler.
     *
     * @return  void
     *
     * @note    Appeler get() dans cette méthode résultera en une récursion.
     */
    protected function populateState() {
    }



}

<?php
/**
 * Part of the ETD Framework Model Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Model;

use EtdSolutions\Table\Table;

use Joomla\Application\AbstractApplication;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Model\AbstractDatabaseModel;
use Joomla\Registry\Registry;

/**
 * Modèle de base
 */
class Model extends AbstractDatabaseModel implements ContainerAwareInterface {

    use ContainerAwareTrait;

    /**
     * @var AbstractApplication L'objet application.
     */
    protected $app;

    /**
     * Indique si l'état interne du modèle est définit
     *
     * @var    boolean
     */
    protected $__state_set = null;

    protected $name;

    /**
     * @var array Un tableau des erreurs.
     */
    protected $errors = array();

    /**
     * Instancie le modèle.
     *
     * @param AbstractApplication $app            L'objet Application.
     * @param DatabaseInterface   $db             L'objet DatabaseInterface.
     * @param Registry            $state          L'état du modèle.
     * @param bool                $ignore_request Utilisé pour ignorer la mise à jour de l'état depuis la requête.
     */
    public function __construct(AbstractApplication $app, DatabaseInterface $db, Registry $state = null, $ignore_request = false) {

        parent::__construct($db, $state);

        $this->app = $app;

        if ($ignore_request) {
            $this->__state_set = true;
        }
    }

    /**
     * Méthode pour instancier un table.
     *
     * @param   string $name    Le nom du Table. Optionnel.
     *
     * @return  Table  Un objet Table
     *
     * @throws  \RuntimeException
     */
    public function getTable($name = null) {

        if (!isset($name)) {
            $name = $this->getName();
        }

        $class = APP_NAMESPACE . "\\Table\\" . ucfirst($name) . "Table";

        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf("Unable to find %s table.", $name), 500);
        }

        $instance = new $class($this->db);

        if (isset($this->container) && in_array("Joomla\\DI\\ContainerAwareInterface", class_implements($instance))) {
            $instance->setContainer($this->getContainer());
        }

        return $instance;
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
     * Set the model state.
     *
     * @param   Registry  $state  The state object.
     *
     * @return  void
     *
     * @since   1.0
     */
    public function setState(Registry $state) {
        $this->__state_set = true;
        parent::setState($state);
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
        if (!empty($error)) {
            $this->errors[] = $error;
        }
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

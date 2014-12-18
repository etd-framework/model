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
use Joomla\Model\AbstractModel;

defined('_JEXEC') or die;

class ErrorModel extends AbstractModel {

    protected $error;

    /**
     * @return array Un tableau correspondant à l'erreur.
     */
    public function getError() {

        if (!isset($this->error)) {
            $this->error = Web::getInstance()->getError();
        }

        return $this->error;
    }

}
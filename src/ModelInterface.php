<?php
/**
 * Part of the ETD Framework Model Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Model;

use Joomla\DI\ContainerAwareInterface;
use Joomla\Model\DatabaseModelInterface;
use Joomla\Model\StatefulModelInterface;

interface ModelInterface extends DatabaseModelInterface, StatefulModelInterface, ContainerAwareInterface {

}

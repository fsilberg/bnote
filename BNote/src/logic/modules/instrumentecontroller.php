<?php

/**
 * Controller for instrument methods in configuration module.
 * @author matti
 *
 */
class InstrumenteController extends DefaultController {

	function start() {
		if(isset($_GET['sub'])) {
			$this->getView()->$_GET['sub']();
		}
		else {
			$this->getView()->start();
		}
	}
}

?>
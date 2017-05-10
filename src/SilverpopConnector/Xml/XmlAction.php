<?php

namespace SilverpopConnector\Xml;

/**
 * Created by IntelliJ IDEA.
 * User: emcnaughton
 * Date: 5/10/17
 * Time: 8:23 AM
 */
class XmlAction {

  public function __construct($params) {
    foreach ($params as $key => $value) {
      if (method_exists($this, 'set' . $key)) {
        $this->{'set' . $key}($value);
      }
    }
  }

}

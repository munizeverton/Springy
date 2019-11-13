<?php

use Springy\Controller;

class Test_Controller extends Controller
{
    /**
     * Action index do controller Test
     */
    public function _default()
    {
        $tests = new Test();
        $tests->query();

        $tpl = $this->_template();
        $tpl->assign('tests', $tests->all());
        $tpl->display();
    }
}

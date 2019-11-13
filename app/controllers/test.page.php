<?php

use Springy\Controller;

class Test_Controller extends Controller
{
    /**
     * Action index do controller Test
     */
    public function _default()
    {
        $tests = $this->getModel();
        $tests->query();

        $tpl = $this->_template();
        $tpl->assign('tests', $tests->all());
        $tpl->display();
    }

    public function new()
    {
        $tests = $this->getModel();
        $input = $this->getInput();

        if (!$input->isPost()) {
            return $this->_default();
        }

        $tests->set('name', $input->get('name'));

        if (!$tests->validate()) {
            $tpl = $this->_template();
            $tpl->assign('messages', $tests->validationErrors());
            $tests = $this->getModel();
            $tests->query();
            $tpl->assign('tests', $tests->all());
            return $tpl->display();
        }

        $tests->save();

        return $this->_default();
    }

    public function edit()
    {
        $tests = $this->getModel();
        $input = $this->getInput();

        if (!$input->isPost()) {
            return $this->_default();
        }

        $tests->query(['id' => $input->get('id')]);

        if (!$tests->count()) {
            return $this->_default();
        }

        $tests->set('name', $input->get('name'));

        if (!$tests->validate()) {
            $tpl = $this->_template();
            $tpl->assign('messages', $tests->validationErrors());
            $tests = $this->getModel();
            $tests->query();
            $tpl->assign('tests', $tests->all());
            return $tpl->display();
        }

        $tests->update(
            ['name' => $input->get('name')],
            ['id' => $input->get('id')]
        );

        return $this->_default();
    }

    public function delete()
    {
        $tests = $this->getModel();
        $input = $this->getInput();

        if (!$input->isPost()) {
            return $this->_default();
        }

        $tests->query(['id' => $input->get('id')]);

        if (!$tests->count()) {
            return $this->_default();
        }

        $tests->delete(['id' => $input->get('id')]);

        return $this->_default();
    }

    private function getModel()
    {
        return new Test();
    }

    private function getInput()
    {
        return new \Springy\Core\Input();
    }
}

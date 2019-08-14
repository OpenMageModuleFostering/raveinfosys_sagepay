<?php

class Raveinfosys_Sagepay_Controller_Observer
{

    //Event: adminhtml_controller_action_predispatch_start
    public function overrideTheme()
    {
        Mage::getDesign()->setArea('adminhtml')
                ->setTheme('sagepay');
    }

}

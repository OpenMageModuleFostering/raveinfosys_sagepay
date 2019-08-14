<?php

class Raveinfosys_Sagepay_Model_System_Config_Source_Dropdown_Values
{
   public function toOptionArray()
    {
        return array(
            array(
                'value' => 'simulator',
                'label' => 'Simulator',
            ),
            array(
                'value' => 'test',
                'label' => 'Test',
            ),
			array(
                'value' => 'live',
                'label' => 'Live',
            ),
        );
    }
}

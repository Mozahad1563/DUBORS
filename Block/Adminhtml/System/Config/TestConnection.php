<?php
/**
 * BrainStation23
 *
 * @category    BrainStation23
 * @package     BrainStation23_Dubors
 * @copyright   Copyright (c) 2026 BrainStation23
 */

declare(strict_types=1);

namespace BrainStation23\Dubors\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class TestConnection extends Field
{
    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    /**
     * Remove the scope label so the row looks clean
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Render button + result banner
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $ajaxUrl = $this->getUrl('dubors/system/testConnection');

        return <<<HTML
<button type="button" id="dubors_test_connection_btn"
        class="action-default scalable"
        style="margin-bottom:8px">
    <span>Test Connection</span>
</button>
<div id="dubors_connection_result" style="display:none;padding:8px 12px;border-radius:4px;font-weight:600;margin-top:4px"></div>

<script>
require(['jquery'], function ($) {
    'use strict';

    $('#dubors_test_connection_btn').on('click', function () {
        var btn    = $(this);
        var result = $('#dubors_connection_result');

        var serviceUrl = $('#dubors_ml_service_service_url').val()
                      || $('[name="groups[ml_service][fields][service_url][value]"]').val();
        var apiKey     = $('#dubors_ml_service_api_key').val()
                      || $('[name="groups[ml_service][fields][api_key][value]"]').val();
        var timeout    = parseInt(
                            $('#dubors_ml_service_service_timeout').val()
                         || $('[name="groups[ml_service][fields][service_timeout][value]"]').val()
                         || 10, 10);

        if (!serviceUrl) {
            result.css({background:'#fef3cd', color:'#856404', border:'1px solid #ffc107'})
                  .text('Please enter the ML Service URL first.')
                  .show();
            return;
        }

        btn.prop('disabled', true).find('span').text('Testing…');
        result.hide();

        $.ajax({
            url:  '{$ajaxUrl}',
            type: 'POST',
            data: {
                service_url: serviceUrl,
                api_key:     apiKey,
                timeout:     timeout,
                form_key:    window.FORM_KEY
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    result.css({background:'#d4edda', color:'#155724', border:'1px solid #28a745'})
                          .html('&#10003; Connected — ' + response.message)
                          .show();
                } else {
                    result.css({background:'#f8d7da', color:'#721c24', border:'1px solid #dc3545'})
                          .html('&#10007; ' + response.message)
                          .show();
                }
            },
            error: function () {
                result.css({background:'#f8d7da', color:'#721c24', border:'1px solid #dc3545'})
                      .text('Request failed — could not reach Magento backend.')
                      .show();
            },
            complete: function () {
                btn.prop('disabled', false).find('span').text('Test Connection');
            }
        });
    });
});
</script>
HTML;
    }
}

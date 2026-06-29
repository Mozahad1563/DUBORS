<?php
declare(strict_types=1);

namespace BrainStation23\Dubors\Block\Adminhtml\Recommendation;

use BrainStation23\Dubors\Model\RecommendationRepository;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class RejectForm extends Template
{
    public function __construct(
        Context $context,
        private readonly RecommendationRepository $recommendationRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getRecommendation()
    {
        $id = (int)$this->getRequest()->getParam('id');
        if (!$id) {
            return null;
        }
        try {
            return $this->recommendationRepository->getById($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getSubmitUrl(): string
    {
        return $this->getUrl('dubors/recommendation/reject');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('dubors/recommendation/');
    }
}

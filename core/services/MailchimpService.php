<?php
namespace Craft\Plugins\Postmaster\Services;

use Craft\Postmaster_TransportModel;
use Craft\Postmaster_MailchimpCampaignModel;
use Craft\Postmaster_MailchimpSubscriberModel;
use Craft\Plugins\Postmaster\Components\BaseService;
use Craft\Plugins\Postmaster\Responses\TransportResponse;
use Guzzle\Http\Client;

class MailchimpService extends BaseService {

	public $name = 'Mailchimp';

	public $id = 'mailchimp';

	protected $requireModels = array(
		'Craft\EmailModel'
	);

	public function registerCpRoutes()
	{
		return array(
			'postmaster/mailchimp/lists' => array('action' => 'postmaster/mailchimp/getLists'),
		);
	}

	public function getLists()
	{
		$client = new Client();

		$request = $client->post($this->getApiUrl('lists/list'), array(), array(
			'apikey' => $this->settings->apiKey
		));

		try
		{
			$response = $request->send();
			$response = json_decode($response->getBody());

			return $response;
		}
		catch(\Exception $e)
		{
			throw $e;
		}
	}

	public function subscribe(Array $data = array())
	{
		$model = new Postmaster_MailchimpSubscriberModel($data);

		try
		{
			$model->subscribe();

			return $model;
		}
		catch(\Exception $e)
		{
			throw $e;
		}
	}

	public function createCampaign(Array $data = array())
	{
		$model = new Postmaster_MailchimpCampaignModel($data);

		try
		{
			$model->create();

			return $model;
		}
		catch(\Exception $e)
		{
			throw $e;
		}
	}

	public function createAndSendCampaign(Array $data = array())
	{
		try
		{
			$model = $this->createCampaign($data);
			$model->send();

			return $model;
		}
		catch(\Exception $e)
		{
			throw $e;
		}
	}

	public function send(Postmaster_TransportModel $model)
	{
		$response = new TransportResponse($model, true);

        foreach($this->settings->listIds as $listId)
        {
			if(!empty($listId))
			{
				if($this->settings->action == 'createAndSend')
				{
			    	try
			    	{
						$this->createAndSendCampaign(array(
				    		'apiKey' => $this->settings->apiKey,
							'type' => $this->settings->campaignType,
							'listId' => $listId,
							'subject' => $model->settings->subject,
							'fromEmail' => $model->settings->fromEmail,
							'fromName' => $model->settings->fromName,
							'toName' => $model->settings->toName,
							'title' => $this->settings->title,
							'html' => $model->settings->htmlBody,
							'text' => $model->settings->body,
							'trackOpens' => (bool) $this->settings->trackOpens,
							'trackHtmlClicks' => (bool) $this->settings->trackHtmlClicks,
							'trackTextClicks' => (bool) $this->settings->trackTextClicks,
							'googleAnalytics' => $this->settings->googleAnalytics,
						));
					}
					catch(\Exception $e)
					{
						$errorResponse = json_decode($e->getResponse()->getBody());

						$response->setSuccess(false);
						$response->setCode($errorResponse->code);
						$response->addError($errorResponse->error);
					}
				}
				else if($this->settings->action == 'subscribe')
				{
					try
					{
						$this->subscribe(array(
							'type' => $this->settings->subscriberType,
							'apiKey' => $this->settings->apiKey,
							'listId' => $listId,
							'email' => $this->settings->subscriberEmail,
							'newEmail' => $this->settings->subscriberNewEmail,
							'groupings' => array(),
							'doubleOptin' => $this->settings->doubleOptin,
							'updateExisting' => $this->settings->updateExisting,
							'replaceInterests' => $this->settings->replaceInterests,
							'sendWelcome' => $this->settings->sendWelcome,
							'profileVars' => $this->settings->profileVars,
						));
					}
					catch(\Exception $e)
					{
			            $errorResponse = json_decode($e->getResponse()->getBody());

			            $response->setCode($errorResponse->code);
			            $response->addError($errorResponse->error);
					}
				}
			}
		}

		return $response;
	}

	public function getInputHtml(Array $data = array())
	{
		return $this->craft()->templates->render('postmaster/services/mailchimp/settings', $data);
	}

	public function getSettingsModelClassName()
	{
		return '\Craft\Postmaster_MailchimpServiceSettingsModel';
	}

	protected function getApiUrl($method)
	{
		$url = 'https://<dc>.api.mailchimp.com/2.0/'.$method.'.json';
		
		return str_replace('<dc>', substr($this->settings->apiKey, strpos($this->settings->apiKey, '-')+1), $url);
	}

}
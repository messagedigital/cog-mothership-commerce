<?php

namespace Message\Mothership\Commerce\Controller\Order;

use Message\Cog\Controller\Controller;

class Document extends Controller
{

	public function printDocument($documentID)
	{
		$document = $this->get('order.document.loader')->getByID($documentID);

		if ($document->type == 'dispatch-label') {

			// @temp Should be moved to file manager
			return $this->render('Message:Mothership:Ecommerce::print-jzebra', array(
				'contents' => explode("\n", file_get_contents($document->file->getRealPath())),
			));
		}

		return $this->redirectToRoute('ms.cp.file_manager.print', array(
			'path' => $document->file->getRealPath()
		));
	}

}
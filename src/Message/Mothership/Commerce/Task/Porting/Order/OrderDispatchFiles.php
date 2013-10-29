<?php

namespace Message\Mothership\Commerce\Task\Porting\Order;

use Message\Mothership\Commerce\Task\Porting\Porting;

class OrderDispatchFiles extends Porting
{
    public function process()
    {
        $uwOld 	 = $this->getFromConnection();
        $uwNew   = $this->getToConnection();

		$old 	 = new \Message\Cog\DB\Query($uwOld);
		$new     = new \Message\Cog\DB\Transaction($uwNew);

		$results = $old->run('
			SELECT
				despatch_packing_slip AS slip,
				despatch_label_data AS label_data,
				order_id,
				despatch_id,
				UNIX_TIMESTAMP(despatch_timestamp) AS created_at
			FROM
				order_despatch
			WHERE
				despatch_packing_slip IS NOT NULL
			AND despatch_timestamp IS NOT NULL'
		);

		$new->add('TRUNCATE order_document');

		$documentSql = '
				INSERT INTO
					order_document
				(
					order_id,
					dispatch_id,
					created_at,
					type,
					url
				)
				VALUES
				(
					:order_id,
					:dispatch_id,
					:created_at,
					:type,
					:url
				)
			';

		foreach ($results as $row) {
			$dirs = array(
				'cog://data/order',
				'cog://data/order/picking',
				'cog://data/order/picking/' . date('Ymd', $row->created_at),
			);
			// Create the directory
			$fileDestination = array_pop($dirs);
			$this->get('filesystem')->mkdir($dirs,0777);
			// Set the details for the packing slip
			$filename = $row->order_id . '_packing-slip';
			$contents = (string) $this->getHtml($row->slip);
			$path     = $fileDestination . '/' . $filename . '.pdf';

			$manager = $this->get('filesystem.stream_wrapper_manager');
			$handler = $manager::getHandler('cog');
			$path    = $handler->getLocalPath($path);

			// Convert file to pdf
			$converter = $this->get('filesystem.conversion.pdf');
			$converter->setHtml($contents);
			$file = $converter->save($path);

			$new->add($documentSql, array(
				'order_id' => $row->order_id,
				'dispatch_id' => $row->despatch_id,
				'created_at' => $row->created_at,
				'type' => 'packing-slip',
				// http://regex101.com/r/jK5rN1
				'url' => preg_replace('/(.+)(data\/order\/picking)(.+)/', 'cog://$2$3', $path)
			));

			// Set the data for the label data
			$filename = $row->order_id .'_'.$row->despatch_id.'_label-data';
			$contents = (string) $row->label_data;
			$path     = $fileDestination . '/' . $filename;

			if ($row->despatch_label_type == 'PNG') {
				$path .= '.png';
			} else {
				$path .= '.txt';
			}

			$manager = $this->get('filesystem.stream_wrapper_manager');
			$handler = $manager::getHandler('cog');
			$path    = $handler->getLocalPath($path);
			// Save the file
			$this->get('filesystem')->dumpFile($path, $contents);

			$new->add($documentSql, array(
				'order_id' => $row->order_id,
				'dispatch_id' => $row->despatch_id,
				'created_at' => $row->created_at,
				'type' => 'dispatch-label',
				// http://regex101.com/r/jK5rN1
				'url' =>  preg_replace('/(.+)(data\/order\/picking)(.+)/', 'cog://$2$3', $path)
			));
		}

		if ($new->commit()) {
        	$this->writeln('<info>Successfully ported order dispatch files</info>');
		}

		return true;
    }

    /**
     * Wrap the content around the rest of the html so we have a similar file to before
     *
     * @param  string $content content to wrap
     *
     * @return string          all the html
     */
    public function getHtml($content)
    {
    	return '<html xmlns="http://www.w3.org/1999/xhtml"><head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Site Admin</title>
	<style>


body {
	background:#FFF;
	width:100%;
}
#wrapper {

}
.page {
	border:none;
	min-height:100%;
	_height:100%;
	page-break-after: always;
    page-break-inside: avoid;
}
.page_last {
	page-break-after: avoid !important;
	margin-bottom: 0 !important;
}
button {
	display:none;
}

/****[ SET UP PRINT DOCUMENT ]*************************/

body {
	background:#EEE;
	color:#000;
	margin:0;
	font-family: Georgia, "Times New Roman", Times, serif;
	font-family:Arial, Helvetica, sans-serif;
}
#wrapper {
	width:800px;
	padding-top:10px;
	margin:0 auto;
}
.page {
	background:#FFF;
	color:#000;
	border:1px solid #AAA;
	padding:10px;
	margin-bottom:10px;
	min-height:1200px;
	_height:1200px;
	zoom:1;
	position:relative;
	page-break-after:always;
}
.page_first {
	background:#FFF;
	color:#000;
	border:1px solid #AAA;
	padding:10px;
	margin-bottom:10px;
	height:28cm;
	position:relative;
}

#wrapper > br {
	display: none !important;
}

/****[ MANIFEST STYLES ]*************************/

table {
	width:100%;
	border-collapse:collapse;
	border-spacing: 0;

	margin-bottom: 40px;
}
td {
	padding: 2px 0 5px;
	border-bottom: 1px solid #ddd;

	color: #333;

	min-height: 14px;
}
	td.noborder {
		border-bottom: 1px solid transparent;
	}
	tr:first-child td {
		padding-top: 17px;
	}
	td b {
		text-transform: uppercase;
		color: #000;
		display: block;

		padding-bottom: 1px;
	}
th {
	text-align:left;
	padding:0 5px 10px;
}
tr.even td {
	background-color:#F6F6F6;
}
button {
	margin:0 0 10px;
	width:100px;
}
td p.picking-description {
	font-size: 0.8em;
	margin-top: 2px;
	margin-bottom: 2px;
}



/****[ PACKING SLIP STYLES ]*************************/

body {
	font: 12px Uniform, sans-serif;
}

h1 {
	padding-top: 85px;
	font-size: 18px;
}

h3 {
	font-size: 16px;
	line-height: 16px;

	font-weight: normal;
}
	h3 b {
		font-size: 20px;
	}

section {
	clear: both;
	overflow: hidden;

	margin-top: 20px;
	border-top: 1px solid #888;

	min-height: 180px;
}
	section.items {
		min-height: 1px;
	}
	section header {
		border-bottom: 1px solid #bbb;

		text-transform: uppercase;
		font-weight: bold;

		overflow: hidden;
	}
		section header h2 {
			padding: 2px 0 5px;
			margin: 0;

			font-size: 12px;
		}

dl, dl + strong, strong + strong, div.section {
	margin: 15px 0;
}
dl {
	position: relative;
	padding-left: 33.333%;

	font-size: 12px;
	font-weight: bold;
	text-transform: uppercase;
}
	dt {
		color: #555;
		position: absolute;
		left: 0;
	}
	dd {
		clear: both;
		padding-bottom: 8px;
	}
		dt:first-child + dd {
			padding-bottom: 22px;
		}

	dd.no-case {
		text-transform: none;
	}

.col {
	float: left;
	width: 16.666%;
}
	.col.two {
		width: 33.333%;
	}
	.col.three {
		width: 50%;
	}
	.col.four {
		width: 66.6666%;
	}

.smaller {
	font-size: 11px;
}


.notes {
	margin-top: 15px;
}
.notes span {
	display: block;
	color: #555;
	font-size: 12px;
	font-weight: bold;
	text-transform: uppercase;
}

.notes p {
	text-transform: uppercase;
	font-weight: bold;
}


/****[ COMMERCIAL INVOICE ]*****************/

.shipping-invoice {
	font-size:0.9em;
	zoom:1;
}
.shipping-invoice h2 {
	margin:0 0 10px;
	font-size:1.4em;
}
.shipping-invoice ul {
	list-style:none;
	margin-left:0;
	margin-top:0;
	padding-left:0;
}
.shipping-invoice li {
	display:block;
	clear:left;
}
.shipping-invoice li strong {
	display:inline-block;
	vertical-align:top;
	width:180px;

}
.shipping-invoice li span {
	display:inline-block;
}

.shipping-invoice p,
.invoice-header h2,
.invoice-footer p {
	clear:left;
	text-align:center;
	margin-top:0;
}
.invoice-header,
.invoice-sender,
.requested-shipment {
	position:relative;
	height:1%;
	clear:left;
	float:left;
	border:1px solid #666;
	padding:10px;
	margin-bottom:10px;
	width:756px;
}
.invoice-header h2 {

}
.invoice-sender .contant-info,
.invoice-sender .financial-info {
	width:40%;
	_height:1%;
	float:right;
	position:relative;
}
.invoice-items,
.invoice-footer {
	clear:left;
	float:left;
	margin-bottom:10px;
	width:100%;
}
.invoice-sender .contant-info {
	float:left;
	width:60%;
}
.invoice-billto,
.invoice-shipto {
	width:60%;
	float:left;
	position:relative;
}
.invoice-billto {
	width:40%;
	float:right;
}


/****[ LABEL PRINTING STYLES ]************************/

.label-wrapper {
	text-align:center;
}
.label-wrapper img {
	height:500px;

}
.label-warning {
	font-weight:bold;
	text-transform:uppercase;
	font-size:0.7em;
	margin:7px;
}
.label-conditions {
	font-size:0.4em;
	line-height:1.3em;
	text-align:justify;
}


/****[ GIFT VOUCHER PRINTING ]************************/

.voucher dl dt {
	font-weight: bold;
}

.voucher dl dd {
	margin-left: 0;
	margin-top: 5px;
	margin-bottom: 15px;
}


/**
 *    @font-face
 */

@font-face {
    font-family: \'Uniform\';
    src: url(\'fonts/uniform-bold.woff\') format(\'woff\');

    font-weight: bold;
    font-style: normal;
}

@font-face {
    font-family: \'Uniform\';
    src: url(\'fonts/uniform.woff\') format(\'woff\');

    font-weight: normal;
    font-style: normal;
}

@font-face {
    font-family: \'Uniform SC\';
    src: url(\'fonts/uniform_sc.woff\') format(\'woff\');

    font-weight: normal;
    font-style: normal;
}

	</style>

<body>

	<div id="wrapper">

        <div class="page first">
'.$content.'
		</div>

    </div>


</body></html>';
    }
}
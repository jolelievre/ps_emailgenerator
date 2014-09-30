<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/1999/REC-html401-19991224/strict.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />
		<title><?php echo t('Message from {shop_name}'); ?></title>
		<link rel="stylesheet" type="text/css" href="<?php echo $emailPublicWebRoot ?>email.css">
		<?php if ($emailLangIsRTL) : ?><link rel="stylesheet" type="text/css" href="<?php echo $emailPublicWebRoot ?>rtl.css"><?php endif; ?>

		<style>
			/****** responsive ********/
			@media only screen and (max-width: 300px){ 
				body {
					width:218px !important;
					margin:auto !important;
				}
				.table {width:195px !important;margin:auto !important;}
				.logo, .titleblock, .linkbelow, .box, .footer, .space_footer{width:auto !important;display: block !important;}		
				span.title{font-size:20px !important;line-height: 23px !important}
				span.subtitle{font-size: 14px !important;line-height: 18px !important;padding-top:10px !important;display:block !important;}		
				td.box p{font-size: 12px !important;font-weight: bold !important;}
				.table-recap table, .table-recap thead, .table-recap tbody, .table-recap th, .table-recap td, .table-recap tr { 
					display: block !important; 
				}
				.table-recap{width: 200px!important;}
				.table-recap tr td, .conf_body td{text-align:center !important;}	
				.address{display: block !important;margin-bottom: 10px !important;}
				.space_address{display: none !important;}	
			} 
							
			@media only screen and (min-width: 301px) and (max-width: 500px) { 
				body {width:425px!important;margin:auto!important;}
				.table {width:400px!important;margin:auto!important;}	
				.logo, .titleblock, .linkbelow, .box, .footer, .space_footer{width:auto!important;display: block!important;}	
				.table-recap table, .table-recap tbody, .table-recap td, .table-recap tr { 
					display: block !important; 
				}
				.table-recap{width: 295px !important;}
				.table-recap tr td, .conf_body td{text-align:center !important;}
				
			} 

			@media only screen and (min-width: 501px) and (max-width: 768px) {
				body {width:478px!important;margin:auto!important;}
				.table {width:450px!important;margin:auto!important;}	
				.logo, .titleblock, .linkbelow, .box, .footer, .space_footer{width:auto!important;display: block!important;}			
			}

						
			/* Mobile */

			@media only screen and (max-device-width: 480px) { 
				body {width:425px!important;margin:auto!important;}
				.table {width:285px;margin:auto!important;}	
				.logo, .titleblock, .linkbelow, .box, .footer, .space_footer{width:auto!important;display: block!important;}
				
				.table-recap{width: 295px!important;}
				.table-recap tr td, .conf_body td{text-align:center!important;}	
				.address{display: block !important;margin-bottom: 10px !important;}
				.space_address{display: none !important;}	
			} 
		</style>

	</head>
	<body style="-webkit-text-size-adjust:none;">
		<table class="table table-mail">
			<tr>
				<td class="space">&nbsp;</td>
				<td align="center">
					<table class="table" bgcolor="#ffffff">
						<tr>
							<td align="center" class="logo" style="border-bottom: 4px solid #333333">
								<a title="{shop_name}" href="{shop_url}">
									<img src="{shop_logo}" alt="{shop_name}" />
								</a>
							</td>
						</tr>

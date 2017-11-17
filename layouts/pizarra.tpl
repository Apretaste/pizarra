<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<style type="text/css">
			@media only screen and (max-width: 600px) {
				#container {
					width: 100%;
				}
			}
			@media only screen and (max-width: 480px) {
				.button {
					display: block !important;
				}
				.button a {
					display: block !important;
					font-size: 18px !important; width: 100% !important;
					max-width: 600px !important;
				}
				.section {
					width: 100%;
					margin: 2px 0px;
					display: block;
				}
				.phone-block {
					display: block;
				}
			}
			body{
				font-family: Arial;
			}
			h1{
				color: #9E100A;
				text-transform: uppercase;
				font-size: 22px;
				font-weight: normal;
				margin-top: 0px;
			}
			.rounded{
				border-radius: 10px;
				background: white;
				padding: 10px;
			}
		</style>
	</head>
	<body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="font-family: Arial;">
		<center>
			<table id="container" bgcolor="#F2F2F2" border="0" cellpadding="0" cellspacing="0" valign="top" align="center" width="600">
				<tr>
					<td width="50"></td>
					<!--logo-->
					<td align="center" valign="middle">
						<span style="color:#9E100A; font-size:60px; font-family:Times;"><i><b>P</b></i></span>
					</td>

					<!--notifications-->
					<td width="50" align="left" valign="top" style="padding-top:10px">
						{if $num_notifications > 0}
							{link href="NOTIFICACIONES" caption="&#9888;{$num_notifications}"}
						{/if}
					</td>
				</tr>

				<!--main section-->
				<tr>
					<td style="padding: 5px 10px 0px 10px;" colspan="3">
						<div class="rounded">
							{include file="$APRETASTE_USER_TEMPLATE"}
						</div>
					</td>
				</tr>

				<!--footer-->
				<tr>
					<td align="center" colspan="3" bgcolor="#F2F2F2" style="padding: 20px 0px;">
						<small>Pizarra &copy; {$smarty.now|date_format:"%Y"}. All rights reserved.</small>
					</td>
				</tr>
			</table>
		</center>
	</body>
</html>

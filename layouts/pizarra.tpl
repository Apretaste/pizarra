<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<style type="text/css">
		{include file="../includes/styles.css"}
	</style>
</head>

<body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="font-family: Arial;">
	<center>
		<table id="container" bgcolor="#F2F2F2" cellpadding="0" cellspacing="0" valign="top" align="center" width="600">
			<tr>
				<td>
					<nav>
						<div class="nav-wrapper white">
							<!--Logo-->
							<span class="brand-logo left">
								<i><b style="color:#9E100A; font-size:40px; font-family:Times;">&nbsp; P</b></i>
							</span>
							<!--notifications & profile-->
							<ul class="right emoji">
								<li>{link href="PIZARRA" caption="📜" style="color:#9E100A;"}</li>
								<li>{link href="PIZARRA PERFIL" caption="👤" style="color:#9E100A;"}</li>
								<li>{link href="PIZARRA CHAT" caption="💬" style="color:#9E100A;"}</li>
								<li>{link href="PIZARRA TEMAS" caption="🏆" style="color:#9E100A; font-size:18px;"}</li>
								<li>
									{if $num_notifications} {assign var="bell" value="🔔"} {assign var="color" value="#9E100A"} {else} {assign var="bell" value="🔕"}
									{assign var="color" value="grey"} {/if} {link href="NOTIFICACIONES pizarra nota chat" caption="{$bell}" style="color:{$color};
									text-decoration: none;"}
								</li>
							</ul>
						</div>
					</nav>
				</td>
			</tr>
			<!--main section-->
			<tr>
				<td style="padding: 5px 10px;">
					<div class="rounded">
						{include file="$APRETASTE_USER_TEMPLATE"}
					</div>
				</td>
			</tr>

			<!--footer-->
			<tr>
				<td align="center" bgcolor="#F2F2F2" style="padding: 20px 0px;">
					⚓ {link href="PIZARRA AYUDA" caption="Ayuda con Pizarra" style="color:#101010;"}
					<br/>
					<small>Pizarra &copy; {$smarty.now|date_format:"%Y"}. All rights reserved</small>
				</td>
			</tr>
		</table>
	</center>
</body>
</html>
<style type="text/css">
	{include file="../includes/styles.css"}
</style>

{if {$APRETASTE_ENVIRONMENT} eq "app" AND $APP_TYPE eq "original"}
	<table width="100%" cellspacing="10">
		<tr align="center" style="background-color:#F2F2F2;">
			<td>{link href="PIZARRA" caption="&#x1F4DC;" style="color:#9E100A; text-decoration:none;"}</td>
			<td>{link href="PIZARRA PERFIL" caption="&#x1F464;" style="color:#9E100A; text-decoration:none;"}</td>
			<td>{link href="PIZARRA CHAT" caption="&#x1F4AC;" style="color:#9E100A; text-decoration:none;"}</td>
			<td>{link href="PIZARRA TEMAS" caption="&#x1F3C6;" style="color:#9E100A; text-decoration:none;"}</td>
		</tr>
	</table>
	{space10}
{/if}

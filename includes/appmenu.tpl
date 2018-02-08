<style type="text/css">
	h1{
		color: #9E100A;
		text-transform: uppercase;
		font-size: 22px;
		font-weight: normal;
		margin-top: 0px;
	}
	hr{
		border: 0;
		height: 0;
		border-top: 1px solid rgba(0, 0, 0, 0.1);
		border-bottom: 1px solid rgba(255, 255, 255, 0.3);
	}
	.online{
		background-color:#74C365;
		font-size:7px;
		padding:2px;
		border-radius:3px;
		color:white;
		font-weight:bold;
	}
</style>

{if {$APRETASTE_ENVIRONMENT} eq "app"}
	<table width="100%" cellspacing="10">
		<tr align="center" style="background-color:#F2F2F2;">
			<td>{link href="PIZARRA" caption="📜" style="color:#9E100A; text-decoration:none;"}</td>
			<td>{link href="PIZARRA PERFIL" caption="👤" style="color:#9E100A; text-decoration:none;"}</td>
			<td>{link href="PIZARRA CHAT" caption="💬" style="color:#9E100A; text-decoration:none;"}</td>
			<td>{link href="PIZARRA TEMAS" caption="🏆" style="color:#9E100A; text-decoration:none;"}</td>
		</tr>
	</table>
	{space10}
{/if}

<style type="text/css">
	{include file="../includes/styles.css"}
</style>
{if {$APRETASTE_ENVIRONMENT} eq "app"} {* AND $APP_TYPE eq "original"*}
<table width="100%" cellspacing="10">
	<tr>
		<nav class="nav-center">
			<div class="nav-wrapper white">
				<ul class="emoji">
					<li>{link href="PIZARRA" caption="&#x1F4DC;" style="color:#9E100A; text-decoration:none;"}</li>
					<li>{link href="PIZARRA PERFIL" caption="&#x1F464;" style="color:#9E100A; text-decoration:none;"}</li>
					<li>{link href="PIZARRA CHAT" caption="&#x1F4AC;" style="color:#9E100A; text-decoration:none;"}</li>
					<li>{link href="PIZARRA TEMAS" caption="&#x1F3C6;" style="color:#9E100A; text-decoration:none;"}</li>
				</ul>
			</div>
		</nav>
	</tr>
</table>
{/if}
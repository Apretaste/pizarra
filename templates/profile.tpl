<!--PROFILE PICTURE-->
<center>
	{if $profile->picture}
		{img src="{$profile->picture_internal}" alt="Picture" width="200" style="border:3px solid black;"}
	{else}
		{noimage width="200" height="200" text="Tristemente ...<br/>Sin foto de perfil :'-("}
	{/if}

	{space10}

	<!--ABOUT ME-->
	<div>{$profile->about_me}</div>

	<!--BUTTONS-->
	{space5}
	{if $isMyOwnProfile}{button href="PERFIL EDITAR" caption="Editar perfil" size="small"}{/if}
	{button href="PIZARRA @{$profile->username}" caption="Ver notas" color="grey" size="small"}
	{button href="CHAT @{$profile->username}" caption="Chatear" color="grey" size="small"}

	{space10}

	<!--REPUTATION-->
	<div style="background-color:#F2F2F2; padding:10px;">
		<h1 style="margin:0px;">Reputaci&oacute;n</h1>
		<span style="font-size:50px;">{$profile->reputation}</span><br/>
		{if $isMyOwnProfile}
			{link href="PIZARRA CATALOGAR" caption="Ganar reputacion" style="font-size:small;"}
		{/if}
	</div>

	<!--MY TOPICS-->
	{if $profile->topics|@count gt 0}
		{space15}
		<h1>Mis temas</h1>
		{foreach from=$profile->topics item=topic}
			{link href="PIZARRA {$topic}" caption="#{$topic}"}
			&nbsp;
		{/foreach}
	{/if}
</center>

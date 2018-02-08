{include file="../includes/appmenu.tpl"}

<center>
	<!--PROFILE PICTURE-->
	{if $profile->picture}
		{img src="{$profile->picture_internal}" alt="Picture" width="200" style="border:3px solid #9E100A; border-radius:10px;"}
	{else}
		{noimage width="200" height="200" text="Tristemente ...<br/>Sin foto de perfil :'-("}
	{/if}

	<!--FLAG AND COUNTRY-->
	{if {$APRETASTE_ENVIRONMENT} eq "web" AND $profile->country}
		{space5}
		<img class="flag" src="/images/flags/{$profile->country|lower}.png" alt="{$profile->country}"/>
		{$profile->location}
	{/if}

	{space10}

	<!--ABOUT ME-->
	<div>{$profile->about_me}</div>

	<!--BUTTONS-->
	{space5}

	{if $isMyOwnProfile}
		{if {$APRETASTE_ENVIRONMENT} eq "web"}
			{button href="PERFIL EDITAR" caption="Editar perfil" size="small"}
		{/if}
		{button href="PIZARRA @{$profile->username}" caption="Mis notas" color="grey" size="small"}
	{else}
		{button href="PIZARRA CHAT @{$profile->username}" caption="Chatear" color="grey" size="small"}
		{button href="PIZARRA @{$profile->username}" caption="Ver notas" color="grey" size="small"}
		{button href="PIZARRA DENUNCIAR @{$profile->username}" caption="Denunciar" desc="m:Por que desea denunciar a este usuario? [El perfil tiene info falsa,Esta impersonando a alguien,Sus notas son ofensivas,Escribe notas falsas o ilegibles,Escribe en temas incorrectos,Promueve comportamiento ilegal,Promueve practicas inmorales]*" popup="true" wait="false" color="red" size="small"}
	{/if}

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
		<h1>Temas</h1>
		{foreach from=$profile->topics item=topic}
			{link href="PIZARRA {$topic}" caption="#{$topic}"}
			&nbsp;
		{/foreach}
	{/if}
</center>

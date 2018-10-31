{include file="../includes/appmenu.tpl"}

{assign var="color" value="black-text"}
{if $profile->gender eq "M"}{assign var="color" value="blue-text"}{/if}
{if $profile->gender eq "F"}{assign var="color" value="pink-text"}{/if}

<div class="card">
	<div class="card-header">
		<!--PROFILE PICTURE-->
		{if $profile->picture}{img src="{$profile->picture_internal}" alt="Picture"}
		{else}<center>{noimage text="Tristemente ...<br/>Sin foto de perfil"}<center>{/if}
	</div>

	<div class="card-content">
		<p class="{$color}">
			{link href="PIZARRA PERFIL @{$profile->username}" caption="@{$profile->username}"}
			{if $profile->location} - {$profile->location}{/if}
		</p>

		{if $profile->topics|@count gt 0}
			<h6>
				{foreach from=$profile->topics item=topic}
					{link href="PIZARRA {$topic}" caption="#{$topic}"}&nbsp;
				{/foreach}
			</h6>
		{/if}

		<p id="desc">{$profile->about_me}</p>
	</div>

	<div class="card-action center">
		{if $isMyOwnProfile}
			{if {$APRETASTE_ENVIRONMENT} eq "web"}
				{button href="PERFIL EDITAR" caption="Editar" size="small"}
			{/if}
			{button href="PIZARRA @{$profile->username}" caption="Mis notas" color="grey" size="small"}
			{button href="PERFIL DESCRIPCION" caption="Describirse" popup="true" desc="a:Describase a su gusto para que los demas lo conozcan, mínimo 100 caracteres*" wait="false" size="small" }
		{else}
			{if $profile->blockedByMe}
				{button href="PERFIL DESBLOQUEAR @{$profile->username}" caption="Desbloquear" color="red" size="small"}
			{else}
				{button href="PIZARRA CHAT @{$profile->username}" caption="Chatear" color="grey" size="small"}
				{button href="PIZARRA @{$profile->username}" caption="Ver notas" color="grey" size="small"}
				{space5}
				{button href="PIZARRA DENUNCIAR @{$profile->username}" caption="Denunciar" desc="m:Por que desea denunciar a este usuario? [El perfil tiene info falsa,Esta impersonando a alguien,Sus notas son ofensivas,Escribe notas falsas o ilegibles,Escribe en temas incorrectos,Promueve comportamiento ilegal,Promueve practicas inmorales]*" popup="true" wait="false" color="red" size="small"}
				{button href="PERFIL BLOQUEAR @{$profile->username}" caption="Bloquear" color="red" size="small"}
			{/if}
		{/if}
	</div>
</div>

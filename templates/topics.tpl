{include file="../layouts/appmenu.tpl"}

<center>
	<h1>Temas mas leidos</h1>
	{foreach from=$topics item=topic}
		{link href="PIZARRA {$topic->name}" caption="#{$topic->name}" style="font-size:{$topic->fontSize}px; color:{$topic->color};"}
		&nbsp;
	{/foreach}

	{space30}

	<h1>Usuarios mas respetados</h1>
	{foreach from=$users item=user}
		<div style="display:inline-block; margin:0px 10px 20px 10px;">
			{if $user->picture}
				{img src="{$user->picture_internal}" alt="{$user->username}" width="60" height="60"}<br/>
			{else}
				{noimage width="60" height="60" text="Sin foto"}
			{/if}
			{link href="PIZARRA PERFIL @{$user->username}" caption="@{$user->username}" style="font-size:small;"}<br/>
			<small><b>{$user->reputation}</b></small>
		</div>
	{/foreach}
</center>

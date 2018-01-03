<center>
	{if $message}
		<div style="background-color:#DFF0D8; color:#3c763d; padding:5px;">&iexcl;Gracias! Ha ganado 5 puntos de reputacion</div>
		{space15}
	{/if}

	<!--PICTURE-->
	{if $person->picture}
		{img src="{$person->picture_internal}" alt="Picture" width="60" height="60"}
	{else}
		{noimage width="60" height="60" text="Sin foto"}
	{/if}

	<!--TEXT-->
	<p>{$note->text}</p>

	{space5}
	<hr/>
	{space5}

	<!--TOPICS-->
	<p><b>&iquest;En que #tema va esta nota?</b></p>

	{foreach from=$topics item=topic}
		{link href="PIZARRA CATALOGAR {$note->id} {$topic}" caption="#{$topic}"}
		&nbsp;&nbsp;
	{/foreach}

	{space10}

	{button href="PIZARRA CATALOGAR {$note->id}" caption="Otro tema" size="small" popup="true" desc="Inserte un nombre de tema"}
	{button href="PIZARRA CATALOGAR" caption="Saltar" size="small" color="grey"}
</center>

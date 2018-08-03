<p>Lo sentimos, el perfil que usted nos solicita no puede ser mostrado debido a que usted ha sido bloqueado por esa persona</p>
<center>
{if $person->blockedByMe}
{button href="PERFIL DESBLOQUEAR @{$person->username}" caption="Desbloquear" color="red"}
{else}
{button href="PERFIL BLOQUEAR @{$person->username}" caption="Bloquear" color="red"}
{/if}
</center>
{foreach from=$blocks item='block'}
    <section id="{$block.id}" class="{$block.classes}">
        {$block.content nofilter}
    </section>
{/foreach}


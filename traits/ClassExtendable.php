<?php namespace KEERill\Pay\Traits;

use Str;

/**
 * Применение сторонних классов к моделям
 */
trait ClassExtendable
{
    /**
     * @var string Название атрибута, в котором лежит класс
     */
    protected $attributeClassName = 'class_name';

    /**
     * Инициализация трейта. Вешаем функции на события
     * @return void
     */
    public static function bootClassExtendable()
    {
        static::extend(function ($model){
            $model->bindEvent('model.afterFetch', function () use ($model) {
                $model->applyCustomClass();
            });
        });
    }

    /**
     * Возвращает отфильтрованный класс
     * @return string
     */
    public function getFilteredClassName($className = false)
    {
        if (!$className && !$this->{$this->attributeClassName}) {
            return false;
        }

        $className = ($className) ?: $this->{$this->attributeClassName};
        
        if (!$className || !class_exists($className)) {
            return false;
        }

        return Str::normalizeClassName($className);
    }

    /**
     * Проверка на валидность класса
     * @return bool
     */
    public function checkInvalidClassName()
    {
        return $this->{$this->attributeClassName} && class_exists($this->{$this->attributeClassName});
    }

    /**
     * Присваивание класса
     * @return void
     */
    public function applyCustomClass($className = false)
    {
        if (!$this->{$this->attributeClassName} = $this->getFilteredClassName($className)) {
            return;
        }

        if (!$this->isClassExtendedWith($this->{$this->attributeClassName})) {
            $this->extendClassWith($this->{$this->attributeClassName});
        }

        if ($this->methodExists('getFilteredParams')) {
            $this->attributes = array_merge($this->getFilteredParams(), $this->attributes);
        }
    }
}

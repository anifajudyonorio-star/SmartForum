<!DOCTYPE html>
<html>
<head>
    <title>Edit Category</title>
</head>
<body>

<h1>Edit Quiz Category</h1>

<form action="{{ route('quiz-categories.update', $quizCategory->id) }}" method="POST">

    @csrf
    @method('PUT')

    <label>Category Name</label><br>

    <input
        type="text"
        name="category_name"
        value="{{ $quizCategory->category_name }}"
    >

    <br><br>

    <label>Description</label><br>

    <textarea name="description">{{ $quizCategory->description }}</textarea>

    <br><br>

    <button type="submit">
        Update Category
    </button>

</form>

</body>
</html>
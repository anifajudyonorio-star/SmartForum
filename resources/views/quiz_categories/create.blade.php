<!DOCTYPE html>
<html>
<head>
    <title>Create Quiz Category</title>
</head>
<body>

    <h1>Create Quiz Category</h1>

    <form action="{{ route('quiz-categories.store') }}" method="POST">

        @csrf

        <label>Category Name</label><br>
        <input type="text" name="category_name"><br><br>

        <label>Description</label><br>
        <textarea name="description"></textarea><br><br>

        <button type="submit">Save Category</button>

    </form>

</body>
</html>
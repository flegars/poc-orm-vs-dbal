<?php

namespace App\Domain\Book;

enum Genre: string
{
    case Fantasy = 'fantasy';
    case ScienceFiction = 'science_fiction';
    case Thriller = 'thriller';
    case Romance = 'romance';
    case History = 'history';
    case Biography = 'biography';
    case Poetry = 'poetry';
    case Children = 'children';
}
